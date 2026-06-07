<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Imports products from a CSV file with three columns: category, name, price.
 *
 * Categories are matched by name (case-insensitively) and created on the fly
 * when missing. Products are matched by category + name, so re-importing the
 * same file updates prices instead of creating duplicates. The parser copes
 * with the quirks of real-world spreadsheet exports: UTF-8 BOM, Windows-1251
 * encoding, comma or semicolon delimiters, and comma decimal separators.
 */
class ProductCsvImporter
{
    /**
     * Words that, when found in the first row, mark it as a header to skip.
     *
     * @var array<int, string>
     */
    private const HEADER_KEYWORDS = ['категория', 'category', 'название', 'наименование', 'name', 'цена', 'price'];

    /**
     * Categories resolved during the current import, keyed by lowercased name.
     *
     * @var array<string, Category>
     */
    private array $categoryCache = [];

    private bool $categoriesLoaded = false;

    public function importFile(string $path): ProductImportResult
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Не удалось прочитать загруженный файл.');
        }

        return $this->import($contents);
    }

    public function import(string $contents): ProductImportResult
    {
        $this->categoryCache = [];
        $this->categoriesLoaded = false;

        $contents = $this->normalizeEncoding($contents);
        $rows = $this->parseRows($contents);

        $result = new ProductImportResult;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;

            if ($this->isBlankRow($row)) {
                continue;
            }

            if ($index === 0 && $this->looksLikeHeader($row)) {
                continue;
            }

            $this->importRow($row, $rowNumber, $result);
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $row
     */
    private function importRow(array $row, int $rowNumber, ProductImportResult $result): void
    {
        $categoryName = trim($row[0] ?? '');
        $name = trim($row[1] ?? '');
        $rawPrice = trim($row[2] ?? '');

        if ($categoryName === '') {
            $result->recordRow($rowNumber, 'не указана категория.');

            return;
        }

        if ($name === '') {
            $result->recordRow($rowNumber, 'не указано название.');

            return;
        }

        $price = $this->parsePrice($rawPrice);

        if ($price === null) {
            $result->recordRow($rowNumber, "некорректная цена «{$rawPrice}».");

            return;
        }

        try {
            $category = $this->resolveCategory($categoryName);
            $product = Product::query()
                ->where('category_id', $category->id)
                ->where('name', $name)
                ->first();

            if ($product) {
                $product->update(['price' => $price]);
                $result->updated++;
            } else {
                Product::create([
                    'category_id' => $category->id,
                    'name' => $name,
                    'slug' => $this->uniqueSlug(Product::class, $name),
                    'price' => $price,
                ]);
                $result->created++;
            }
        } catch (Throwable $e) {
            $result->recordRow($rowNumber, 'не удалось сохранить — '.$e->getMessage());
        }
    }

    private function resolveCategory(string $name): Category
    {
        $this->ensureCategoriesLoaded();

        $key = mb_strtolower($name);

        if (isset($this->categoryCache[$key])) {
            return $this->categoryCache[$key];
        }

        $category = Category::create([
            'name' => $name,
            'slug' => $this->uniqueSlug(Category::class, $name),
        ]);

        return $this->categoryCache[$key] = $category;
    }

    /**
     * Load existing categories into the cache once per import so matching is
     * case-insensitive and Unicode-aware in PHP, independent of the database
     * collation (SQLite's LOWER(), for instance, ignores Cyrillic).
     */
    private function ensureCategoriesLoaded(): void
    {
        if ($this->categoriesLoaded) {
            return;
        }

        Category::all()->each(function (Category $category): void {
            $this->categoryCache[mb_strtolower($category->name)] = $category;
        });

        $this->categoriesLoaded = true;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function uniqueSlug(string $modelClass, string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'item';
        }

        $slug = $base;
        $suffix = 1;

        while ($modelClass::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    /**
     * Normalize a price cell into a float, accepting spaces, the ruble sign and
     * a comma decimal separator. Returns null when the value isn't numeric.
     */
    private function parsePrice(string $value): ?float
    {
        $value = str_replace(["\u{00A0}", ' ', '₽', 'руб', 'р.'], '', $value);
        $value = str_replace(',', '.', $value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        $price = (float) $value;

        return $price >= 0 ? $price : null;
    }

    private function normalizeEncoding(string $contents): string
    {
        $contents = preg_replace('/^\x{FEFF}/u', '', $contents) ?? $contents;
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        $encoding = mb_detect_encoding($contents, ['UTF-8', 'Windows-1251', 'ISO-8859-1'], true);

        if ($encoding !== false && $encoding !== 'UTF-8') {
            $contents = mb_convert_encoding($contents, 'UTF-8', $encoding);
        }

        return $contents;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseRows(string $contents): array
    {
        $delimiter = $this->detectDelimiter($contents);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $rows[] = array_map(static fn ($value) => (string) ($value ?? ''), $row);
        }

        fclose($handle);

        return $rows;
    }

    private function detectDelimiter(string $contents): string
    {
        $firstLine = strtok($contents, "\n");

        if ($firstLine === false) {
            return ',';
        }

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    /**
     * @param  array<int, string>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $row
     */
    private function looksLikeHeader(array $row): bool
    {
        foreach ($row as $value) {
            if (in_array(mb_strtolower(trim($value)), self::HEADER_KEYWORDS, true)) {
                return true;
            }
        }

        return false;
    }
}
