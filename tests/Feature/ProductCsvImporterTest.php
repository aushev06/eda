<?php

use App\Models\Category;
use App\Models\Product;
use App\Support\ProductCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports products and auto-creates categories', function () {
    $csv = <<<'CSV'
    категория,название,цена
    Пицца,Маргарита,499
    Пицца,Пепперони,599
    Напитки,Кола 0.5,120
    CSV;

    $result = (new ProductCsvImporter)->import($csv);

    expect($result->created)->toBe(3)
        ->and($result->updated)->toBe(0)
        ->and($result->skipped)->toBe(0)
        ->and($result->errors)->toBe([]);

    expect(Category::count())->toBe(2);
    expect(Product::where('name', 'Маргарита')->first()->price)->toEqual('499.00');
});

it('reuses existing categories case-insensitively', function () {
    Category::create(['name' => 'Пицца', 'slug' => 'pizza']);

    $result = (new ProductCsvImporter)->import("пицца,Маргарита,499\n");

    expect($result->created)->toBe(1);
    expect(Category::count())->toBe(1);
    expect(Product::first()->category_id)->toBe(Category::first()->id);
});

it('updates the price on re-import instead of duplicating', function () {
    $importer = new ProductCsvImporter;
    $importer->import("Пицца,Маргарита,499\n");

    $result = $importer->import("Пицца,Маргарита,549\n");

    expect($result->created)->toBe(0)
        ->and($result->updated)->toBe(1);
    expect(Product::count())->toBe(1);
    expect(Product::first()->price)->toEqual('549.00');
});

it('parses semicolon delimiters and comma decimal prices', function () {
    $result = (new ProductCsvImporter)->import("Напитки;Кола;120,50\n");

    expect($result->created)->toBe(1);
    expect(Product::first()->price)->toEqual('120.50');
});

it('skips header, blank rows and reports invalid rows', function () {
    $csv = "категория;название;цена\n\nПицца;Маргарита;499\n;Без категории;100\nПицца;Без цены;abc\n";

    $result = (new ProductCsvImporter)->import($csv);

    expect($result->created)->toBe(1)
        ->and($result->skipped)->toBe(2)
        ->and($result->errors)->toHaveCount(2);
});

it('generates unique slugs for products that share a name', function () {
    $csv = "Пицца,Маргарита,499\nНапитки,Маргарита,300\n";

    $result = (new ProductCsvImporter)->import($csv);

    expect($result->created)->toBe(2);
    expect(Product::pluck('slug')->unique())->toHaveCount(2);
});

it('decodes Windows-1251 encoded files', function () {
    $csv = mb_convert_encoding("Пицца,Маргарита,499\n", 'Windows-1251', 'UTF-8');

    $result = (new ProductCsvImporter)->import($csv);

    expect($result->created)->toBe(1);
    expect(Category::first()->name)->toBe('Пицца');
});
