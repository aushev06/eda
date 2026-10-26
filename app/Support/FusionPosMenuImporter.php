<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Services\FusionPos\FusionPosClient;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Syncs the site menu with FusionPOS: dish categories, dishes, modifier
 * groups, modifier options and which dishes each modifier group applies to.
 *
 * In FusionPOS a modifier group is a menu category flagged `is_modifier`; its
 * options are the menu positions inside it. A group is attached to dishes
 * directly or to whole dish categories (`modifier_positions`).
 *
 * Records are matched by `fusion_pos_id`, falling back to the name for
 * categories and products created on the site before the first sync. A sync
 * refreshes prices and availability but keeps names, photos and the category
 * curated on the site. Modifier groups not linked to FusionPOS are untouched.
 */
class FusionPosMenuImporter
{
    public const DEFAULT_CATEGORY_NAME = 'Из FusionPOS';

    /**
     * Site categories keyed by FusionPOS category id.
     *
     * @var array<int, Category>
     */
    private array $categoriesByFusionId = [];

    /**
     * Site modifier groups keyed by FusionPOS category id.
     *
     * @var array<int, ModifierGroup>
     */
    private array $groupsByFusionId = [];

    private ?Category $defaultCategory = null;

    public function __construct(private FusionPosClient $client) {}

    public function import(bool $activateNewProducts = false): ProductImportResult
    {
        return $this->importMenu(
            $this->client->categories(),
            $this->client->menuItems(),
            $this->client->modifierLinks(),
            $activateNewProducts,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories  Flat list from `GET /api/v1/menucategory`.
     * @param  array<int, array<string, mixed>>  $items  Positions from `GET /api/v1/menu`.
     * @param  array<int, array<string, mixed>>  $modifierLinks  Modifier categories from `GET /api/v2/menu-categories`.
     */
    public function importMenu(array $categories, array $items, array $modifierLinks = [], bool $activateNewProducts = false): ProductImportResult
    {
        $this->categoriesByFusionId = [];
        $this->groupsByFusionId = [];
        $this->defaultCategory = null;

        $result = new ProductImportResult;

        DB::transaction(function () use ($categories, $items, $modifierLinks, $activateNewProducts, $result): void {
            $this->syncCategories(array_filter($categories, fn (array $category): bool => ! $this->isModifierCategory($category)));
            $this->syncModifierGroups(array_filter($categories, fn (array $category): bool => $this->isModifierCategory($category)), $items, $result);

            $this->syncItems($items, $activateNewProducts, $result);
            $this->syncModifierLinks($modifierLinks);
        });

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     */
    private function syncCategories(array $categories): void
    {
        $linked = Category::query()->whereNotNull('fusion_pos_id')->get()->keyBy('fusion_pos_id');
        $unlinkedByName = $this->keyByName(Category::query()->whereNull('fusion_pos_id')->get());

        foreach ($categories as $data) {
            $id = (int) ($data['id'] ?? 0);
            $name = trim((string) ($data['category_name'] ?? ''));

            if ($id <= 0 || $name === '') {
                continue;
            }

            $isActive = (bool) ($data['active'] ?? true);
            $category = $linked->get($id) ?? $unlinkedByName->pull(mb_strtolower($name));

            if ($category) {
                $category->update(['fusion_pos_id' => $id] + ($isActive ? [] : ['is_active' => false]));
            } else {
                $category = Category::create([
                    'fusion_pos_id' => $id,
                    'name' => $name,
                    'slug' => $this->uniqueSlug(Category::class, $name),
                    'sort_order' => (int) ($data['sortnum'] ?? 0),
                    'is_active' => $isActive,
                ]);
            }

            $this->categoriesByFusionId[$id] = $category;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncModifierGroups(array $categories, array $items, ProductImportResult $result): void
    {
        $optionCounts = collect($items)->countBy(fn (array $item): int => (int) ($item['category_id'] ?? 0));
        $linked = ModifierGroup::query()->whereNotNull('fusion_pos_id')->get()->keyBy('fusion_pos_id');

        foreach ($categories as $data) {
            $id = (int) ($data['id'] ?? 0);
            $name = trim((string) ($data['category_name'] ?? ''));

            if ($id <= 0 || $name === '') {
                continue;
            }

            $isRequired = ($data['is_modifier_required'] ?? 'no') === 'yes';
            $isSingle = ($data['modifier_type'] ?? 'multi') === 'single';

            $rules = [
                'is_required' => $isRequired,
                'min_select' => $isRequired ? 1 : 0,
                'max_select' => $isSingle ? 1 : max(1, (int) $optionCounts->get($id, 0)),
            ];

            $group = $linked->get($id);

            if ($group) {
                $group->update($rules);
            } else {
                $group = ModifierGroup::create(['fusion_pos_id' => $id, 'name' => $name] + $rules);
            }

            $this->groupsByFusionId[$id] = $group;
            $result->modifierGroups++;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(array $items, bool $activateNewProducts, ProductImportResult $result): void
    {
        $linkedProducts = Product::query()->whereNotNull('fusion_pos_id')->get()->keyBy('fusion_pos_id');
        $unlinkedProductsByName = $this->keyByName(Product::query()->whereNull('fusion_pos_id')->get());
        $linkedModifiers = Modifier::query()->whereNotNull('fusion_pos_id')->get()->keyBy('fusion_pos_id');

        $seenProductIds = [];
        $seenModifierIds = [];

        foreach ($items as $item) {
            $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
            $name = trim((string) ($item['name'] ?? ''));
            $price = $item['price'] ?? null;
            $categoryId = (int) ($item['category_id'] ?? 0);

            if ($id === false) {
                $result->recordSkip('Позиция без id пропущена.');

                continue;
            }

            if ($name === '') {
                $result->recordSkip("Позиция #{$id}: не указано название.");

                continue;
            }

            if (! is_numeric($price) || (float) $price < 0) {
                $result->recordSkip("Позиция #{$id} «{$name}»: некорректная цена.");

                continue;
            }

            try {
                if (isset($this->groupsByFusionId[$categoryId])) {
                    $this->syncModifier($linkedModifiers->get($id), $this->groupsByFusionId[$categoryId], $id, $name, $item);
                    $seenModifierIds[] = $id;
                    $result->modifiers++;

                    continue;
                }

                if (($item['is_modifier'] ?? 'no') === 'yes') {
                    $result->recordSkip("Позиция #{$id} «{$name}»: модификатор вне группы модификаторов.");

                    continue;
                }

                $seenProductIds[] = $id;
                $product = $linkedProducts->get($id) ?? $unlinkedProductsByName->pull(mb_strtolower($name));
                $isActive = (bool) ($item['active'] ?? true);

                if ($product) {
                    $product->update([
                        'fusion_pos_id' => $id,
                        'fusion_pos_category_id' => $categoryId ?: null,
                        'price' => (float) $price,
                    ] + ($isActive ? [] : ['is_active' => false]));
                    $result->updated++;

                    continue;
                }

                Product::create([
                    'category_id' => ($this->categoriesByFusionId[$categoryId] ?? $this->defaultCategory())->id,
                    'fusion_pos_id' => $id,
                    'fusion_pos_category_id' => $categoryId ?: null,
                    'name' => $name,
                    'slug' => $this->uniqueSlug(Product::class, $name),
                    'price' => (float) $price,
                    'weight_grams' => $this->parseWeight($item['weight'] ?? null),
                    'sort_order' => (int) ($item['sortnum'] ?? 0),
                    'is_active' => $activateNewProducts && $isActive,
                ]);
                $result->created++;
            } catch (Throwable $e) {
                $result->recordSkip("Позиция #{$id} «{$name}»: не удалось сохранить — {$e->getMessage()}");
            }
        }

        if ($seenProductIds !== []) {
            $result->deactivated = Product::query()
                ->whereNotNull('fusion_pos_id')
                ->whereNotIn('fusion_pos_id', $seenProductIds)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        if ($items !== []) {
            Modifier::query()
                ->whereNotNull('fusion_pos_id')
                ->whereNotIn('fusion_pos_id', $seenModifierIds ?: [0])
                ->update(['is_active' => false]);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function syncModifier(?Modifier $modifier, ModifierGroup $group, int $id, string $name, array $item): void
    {
        $attributes = [
            'modifier_group_id' => $group->id,
            'price_delta' => (float) $item['price'],
            'is_active' => (bool) ($item['active'] ?? true),
        ];

        if ($modifier) {
            $modifier->update($attributes);

            return;
        }

        Modifier::create($attributes + [
            'fusion_pos_id' => $id,
            'name' => $name,
            'sort_order' => (int) ($item['sortnum'] ?? 0),
        ]);
    }

    /**
     * Attach each FusionPOS modifier group to the dishes it applies to, either
     * directly or through the dish's FusionPOS category. Inactive groups and
     * groups that vanished from FusionPOS are detached from every dish.
     *
     * @param  array<int, array<string, mixed>>  $modifierLinks
     */
    private function syncModifierLinks(array $modifierLinks): void
    {
        $linksByGroupId = collect($modifierLinks)->keyBy(fn (array $link): int => (int) ($link['id'] ?? 0));

        ModifierGroup::query()->whereNotNull('fusion_pos_id')->get()->each(function (ModifierGroup $group) use ($linksByGroupId): void {
            $link = $linksByGroupId->get($group->fusion_pos_id);

            if (! $link || ! ($link['active'] ?? true) || ! isset($this->groupsByFusionId[$group->fusion_pos_id])) {
                $group->products()->sync([]);

                return;
            }

            $dishIds = array_column($link['modifierMenus'] ?? [], 'id');
            $dishCategoryIds = array_column($link['modifierCategoryMenus'] ?? [], 'id');

            $productIds = Product::query()
                ->where(fn ($query) => $query
                    ->whereIn('fusion_pos_id', $dishIds ?: [0])
                    ->orWhereIn('fusion_pos_category_id', $dishCategoryIds ?: [0]))
                ->pluck('id');

            $group->products()->sync($productIds);
        });
    }

    /**
     * @param  array<string, mixed>  $category
     */
    private function isModifierCategory(array $category): bool
    {
        return ($category['is_modifier'] ?? 'no') === 'yes';
    }

    private function defaultCategory(): Category
    {
        return $this->defaultCategory ??= Category::query()->where('name', self::DEFAULT_CATEGORY_NAME)->first()
            ?? Category::create([
                'name' => self::DEFAULT_CATEGORY_NAME,
                'slug' => $this->uniqueSlug(Category::class, self::DEFAULT_CATEGORY_NAME),
            ]);
    }

    /**
     * FusionPOS stores weight as free text ("40", "шт", "г"); only plain
     * numbers are meaningful as grams.
     */
    private function parseWeight(mixed $weight): ?int
    {
        $weight = trim((string) $weight);

        return ctype_digit($weight) && (int) $weight > 0 ? (int) $weight : null;
    }

    /**
     * @template TModel of Category|Product
     *
     * @param  EloquentCollection<int, TModel>  $models
     * @return EloquentCollection<string, TModel>
     */
    private function keyByName(EloquentCollection $models): EloquentCollection
    {
        return $models->keyBy(fn (Category|Product $model): string => mb_strtolower(trim($model->name)));
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function uniqueSlug(string $modelClass, string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $suffix = 1;

        while ($modelClass::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
