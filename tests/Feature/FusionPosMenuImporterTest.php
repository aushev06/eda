<?php

use App\Filament\Pages\ImportFusionPosMenu;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\User;
use App\Support\FusionPosMenuImporter;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Settings::flush();
    Settings::setMany([
        'fusionpos.url' => 'https://cafe.fusionpos.test',
        'fusionpos.token' => 'secret-token',
    ]);
});

/**
 * @param  array<int, array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function fusionPosPage(array $items, int $pageCount = 1): array
{
    return ['success' => true, 'data' => ['items' => $items, '_meta' => ['pageCount' => $pageCount]]];
}

it('fetches categories, every page of the menu and modifier links with the bearer token', function () {
    Http::fake([
        'cafe.fusionpos.test/api/v1/menucategory*' => Http::response(fusionPosPage([
            ['id' => 2, 'category_name' => 'Напитки', 'is_modifier' => 'no', 'active' => true],
            ['id' => 17, 'category_name' => 'Вкус', 'is_modifier' => 'yes', 'is_modifier_required' => 'yes', 'modifier_type' => 'single', 'active' => true],
        ])),
        'cafe.fusionpos.test/api/v1/menu*page=1*' => Http::response(fusionPosPage([['id' => 1, 'name' => 'Сок', 'price' => '150.00', 'category_id' => 2]], 2)),
        'cafe.fusionpos.test/api/v1/menu*page=2*' => Http::response(fusionPosPage([['id' => 225, 'name' => 'Яблоко', 'price' => 0, 'category_id' => 17, 'is_modifier' => 'yes']], 2)),
        'cafe.fusionpos.test/api/v2/menu-categories*' => Http::response(['items' => [
            ['id' => 17, 'active' => true, 'modifierMenus' => [['id' => 1]], 'modifierCategoryMenus' => []],
        ]]),
    ]);

    $result = app(FusionPosMenuImporter::class)->import();

    $juice = Product::where('fusion_pos_id', 1)->first();

    expect($result->created)->toBe(1)
        ->and($result->modifierGroups)->toBe(1)
        ->and($result->modifiers)->toBe(1)
        ->and($juice->price)->toEqual('150.00')
        ->and($juice->category->name)->toBe('Напитки')
        ->and($juice->modifierGroups->pluck('name')->all())->toBe(['Вкус']);

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer secret-token')
        && str_contains(urldecode($request->url()), '"is_deleted":"no"'));
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api/v2/menu-categories')
        && str_contains($request->url(), 'modifierIs=yes'));
    Http::assertSentCount(4);
});

it('creates new products hidden in the FusionPOS category by default', function () {
    $result = app(FusionPosMenuImporter::class)->importMenu([], [['id' => 10, 'name' => 'Хинкали', 'price' => 90]]);

    $product = Product::where('fusion_pos_id', 10)->first();

    expect($result->created)->toBe(1)
        ->and($product->is_active)->toBeFalse()
        ->and($product->category->name)->toBe(FusionPosMenuImporter::DEFAULT_CATEGORY_NAME);
});

it('can activate new products right away', function () {
    app(FusionPosMenuImporter::class)->importMenu([], [['id' => 10, 'name' => 'Хинкали', 'price' => 90]], activateNewProducts: true);

    expect(Product::first()->is_active)->toBeTrue();
});

it('links existing products by name and only updates the price', function () {
    $category = Category::factory()->create(['name' => 'Пицца']);
    $product = Product::factory()->for($category)->create(['name' => 'Маргарита', 'price' => 450]);

    $result = app(FusionPosMenuImporter::class)->importMenu([], [['id' => 7, 'name' => 'маргарита', 'price' => 499]]);

    expect($result->updated)->toBe(1)->and($result->created)->toBe(0);

    $product->refresh();
    expect($product->fusion_pos_id)->toBe(7)
        ->and($product->price)->toEqual('499.00')
        ->and($product->name)->toBe('Маргарита')
        ->and($product->category_id)->toBe($category->id);
});

it('matches by FusionPOS id on re-import even after renaming on the site', function () {
    $product = Product::factory()->create(['fusion_pos_id' => 7, 'name' => 'Пицца Маргарита', 'price' => 450]);

    $result = app(FusionPosMenuImporter::class)->importMenu([], [['id' => 7, 'name' => 'Маргарита', 'price' => 520]]);

    expect($result->updated)->toBe(1);
    expect(Product::count())->toBe(1);
    expect($product->refresh()->price)->toEqual('520.00');
});

it('hides linked products that disappeared from FusionPOS', function () {
    $kept = Product::factory()->create(['fusion_pos_id' => 1]);
    $removed = Product::factory()->create(['fusion_pos_id' => 2]);
    $manual = Product::factory()->create(['fusion_pos_id' => null]);

    $result = app(FusionPosMenuImporter::class)->importMenu([], [['id' => 1, 'name' => $kept->name, 'price' => 100]]);

    expect($result->deactivated)->toBe(1)
        ->and($kept->refresh()->is_active)->toBeTrue()
        ->and($removed->refresh()->is_active)->toBeFalse()
        ->and($manual->refresh()->is_active)->toBeTrue();
});

it('does not hide anything when the menu comes back empty', function () {
    $product = Product::factory()->create(['fusion_pos_id' => 1]);

    $result = app(FusionPosMenuImporter::class)->importMenu([], []);

    expect($result->deactivated)->toBe(0)
        ->and($product->refresh()->is_active)->toBeTrue();
});

it('skips invalid items and reports them', function () {
    $result = app(FusionPosMenuImporter::class)->importMenu([], [
        ['name' => 'Без id', 'price' => 100],
        ['id' => 2, 'name' => '', 'price' => 100],
        ['id' => 3, 'name' => 'Без цены', 'price' => null],
        ['id' => 4, 'name' => 'Норм', 'price' => 100],
    ]);

    expect($result->created)->toBe(1)
        ->and($result->skipped)->toBe(3)
        ->and($result->errors)->toHaveCount(3);
});

it('saves credentials and imports from the admin page', function () {
    $this->actingAs(User::factory()->create());

    Http::fake([
        'new.fusionpos.test/api/v1/menucategory*' => Http::response(fusionPosPage([])),
        'new.fusionpos.test/api/v1/menu*' => Http::response(fusionPosPage([['id' => 5, 'name' => 'Чай', 'price' => 80]])),
        'new.fusionpos.test/api/v2/*' => Http::response(['items' => []]),
    ]);

    Livewire::test(ImportFusionPosMenu::class)
        ->set('data.url', 'https://new.fusionpos.test/')
        ->set('data.token', 'new-token')
        ->set('data.activate_new', true)
        ->call('import')
        ->assertHasNoFormErrors()
        ->assertNotified('Меню загружено');

    expect(Settings::get('fusionpos.url'))->toBe('https://new.fusionpos.test')
        ->and(Settings::get('fusionpos.token'))->toBe('new-token')
        ->and(Product::where('fusion_pos_id', 5)->first()->is_active)->toBeTrue();
});

/**
 * @return array<int, array<string, mixed>>
 */
function fusionPosCategories(): array
{
    return [
        ['id' => 2, 'category_name' => 'Напитки', 'is_modifier' => 'no', 'active' => true, 'sortnum' => 2],
        ['id' => 22, 'category_name' => 'Кофе', 'is_modifier' => 'no', 'active' => true, 'sortnum' => 7],
        ['id' => 17, 'category_name' => 'Вкус', 'is_modifier' => 'yes', 'is_modifier_required' => 'yes', 'modifier_type' => 'single', 'active' => true],
        ['id' => 23, 'category_name' => 'Сиропы', 'is_modifier' => 'yes', 'is_modifier_required' => 'no', 'modifier_type' => 'multi', 'active' => true],
        ['id' => 13, 'category_name' => 'Лёд', 'is_modifier' => 'yes', 'is_modifier_required' => 'no', 'modifier_type' => 'multi', 'active' => false],
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function fusionPosItems(): array
{
    return [
        ['id' => 1, 'name' => 'Сок', 'price' => 150, 'category_id' => 2, 'weight' => '300', 'active' => true],
        ['id' => 2, 'name' => 'Латте', 'price' => 250, 'category_id' => 22, 'weight' => 'шт', 'active' => true],
        ['id' => 3, 'name' => 'Капучино', 'price' => 230, 'category_id' => 22, 'active' => false],
        ['id' => 225, 'name' => 'Яблоко', 'price' => 0, 'category_id' => 17, 'is_modifier' => 'yes', 'active' => true],
        ['id' => 226, 'name' => 'Вишня', 'price' => 0, 'category_id' => 17, 'is_modifier' => 'yes', 'active' => true],
        ['id' => 300, 'name' => 'Карамель', 'price' => 40, 'category_id' => 23, 'is_modifier' => 'yes', 'active' => true],
        ['id' => 301, 'name' => 'Ваниль', 'price' => 40, 'category_id' => 23, 'is_modifier' => 'yes', 'active' => true],
        ['id' => 310, 'name' => 'Кубики', 'price' => 20, 'category_id' => 13, 'is_modifier' => 'yes', 'active' => true],
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function fusionPosModifierLinks(): array
{
    return [
        ['id' => 17, 'active' => true, 'modifierMenus' => [['id' => 1]], 'modifierCategoryMenus' => []],
        ['id' => 23, 'active' => true, 'modifierMenus' => [], 'modifierCategoryMenus' => [['id' => 22]]],
        ['id' => 13, 'active' => false, 'modifierMenus' => [], 'modifierCategoryMenus' => [['id' => 2]]],
    ];
}

it('imports dish categories and places new products into them', function () {
    app(FusionPosMenuImporter::class)->importMenu(fusionPosCategories(), fusionPosItems(), fusionPosModifierLinks(), activateNewProducts: true);

    expect(Category::pluck('name')->sort()->values()->all())->toBe(['Кофе', 'Напитки']);

    $juice = Product::where('fusion_pos_id', 1)->first();
    $cappuccino = Product::where('fusion_pos_id', 3)->first();

    expect($juice->category->fusion_pos_id)->toBe(2)
        ->and($juice->weight_grams)->toBe(300)
        ->and(Product::where('fusion_pos_id', 2)->first()->weight_grams)->toBeNull()
        ->and($cappuccino->is_active)->toBeFalse()
        ->and(Product::count())->toBe(3);
});

it('imports modifier groups with options instead of products', function () {
    $result = app(FusionPosMenuImporter::class)->importMenu(fusionPosCategories(), fusionPosItems(), fusionPosModifierLinks());

    $flavour = ModifierGroup::where('fusion_pos_id', 17)->first();
    $syrups = ModifierGroup::where('fusion_pos_id', 23)->first();

    expect($result->modifierGroups)->toBe(3)
        ->and($result->modifiers)->toBe(5)
        ->and($flavour->is_required)->toBeTrue()
        ->and($flavour->min_select)->toBe(1)
        ->and($flavour->max_select)->toBe(1)
        ->and($flavour->modifiers->pluck('name')->all())->toContain('Яблоко', 'Вишня')
        ->and($syrups->is_required)->toBeFalse()
        ->and($syrups->min_select)->toBe(0)
        ->and($syrups->max_select)->toBe(2)
        ->and(Modifier::where('fusion_pos_id', 300)->first()->price_delta)->toEqual('40.00')
        ->and(Product::where('name', 'Яблоко')->exists())->toBeFalse();
});

it('attaches modifier groups to dishes directly and through dish categories', function () {
    app(FusionPosMenuImporter::class)->importMenu(fusionPosCategories(), fusionPosItems(), fusionPosModifierLinks());

    $groupsOf = fn (int $fusionPosId): array => Product::where('fusion_pos_id', $fusionPosId)->first()
        ->modifierGroups->pluck('fusion_pos_id')->sort()->values()->all();

    expect($groupsOf(1))->toBe([17])
        ->and($groupsOf(2))->toBe([23])
        ->and($groupsOf(3))->toBe([23]);
});

it('keeps manual modifier groups and drops links of groups removed in FusionPOS', function () {
    $importer = app(FusionPosMenuImporter::class);
    $importer->importMenu(fusionPosCategories(), fusionPosItems(), fusionPosModifierLinks());

    $latte = Product::where('fusion_pos_id', 2)->first();
    $manualGroup = ModifierGroup::factory()->create(['name' => 'Подача']);
    $latte->modifierGroups()->attach($manualGroup);

    $linksWithoutSyrups = array_values(array_filter(fusionPosModifierLinks(), fn (array $link): bool => $link['id'] !== 23));
    $importer->importMenu(fusionPosCategories(), fusionPosItems(), $linksWithoutSyrups);

    expect($latte->fresh()->modifierGroups->pluck('name')->all())->toBe(['Подача']);
});

it('updates modifier prices and hides options removed in FusionPOS', function () {
    $importer = app(FusionPosMenuImporter::class);
    $importer->importMenu(fusionPosCategories(), fusionPosItems(), fusionPosModifierLinks());

    $items = array_values(array_filter(fusionPosItems(), fn (array $item): bool => $item['id'] !== 301));
    $items = array_map(fn (array $item): array => $item['id'] === 300 ? ['price' => 55] + $item : $item, $items);

    $importer->importMenu(fusionPosCategories(), $items, fusionPosModifierLinks());

    expect(Modifier::where('fusion_pos_id', 300)->first()->price_delta)->toEqual('55.00')
        ->and(Modifier::where('fusion_pos_id', 301)->first()->is_active)->toBeFalse()
        ->and(Modifier::count())->toBe(5);
});

it('shows an error notification when the API fails', function () {
    $this->actingAs(User::factory()->create());

    Http::fake(['cafe.fusionpos.test/*' => Http::response(['success' => false], 401)]);

    Livewire::test(ImportFusionPosMenu::class)
        ->call('import')
        ->assertNotified('Не удалось получить меню из FusionPOS');

    expect(Product::count())->toBe(0);
});

it('renders the import page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(ImportFusionPosMenu::getUrl())->assertSuccessful();
});
