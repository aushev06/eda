<?php

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\ModifierGroups\ModifierGroupResource;
use App\Filament\Resources\ModifierGroups\Pages\EditModifierGroup;
use App\Filament\Resources\ModifierGroups\RelationManagers\ModifiersRelationManager;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function livewire(string $component, array $params = []): Testable
{
    return Livewire::test($component, $params);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the category list page', function () {
    Category::factory()->count(3)->create();

    $this->get(CategoryResource::getUrl('index'))
        ->assertSuccessful();
});

it('creates a category through the form', function () {
    livewire(CreateCategory::class)
        ->fillForm([
            'name' => 'Десерты',
            'slug' => 'desserts',
            'sort_order' => 10,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Category::where('slug', 'desserts')->exists())->toBeTrue();
});

it('requires a name and slug on category form', function () {
    livewire(CreateCategory::class)
        ->fillForm([
            'name' => '',
            'slug' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'slug' => 'required']);
});

it('renders the product list page', function () {
    Product::factory()->count(3)->create();

    $this->get(ProductResource::getUrl('index'))
        ->assertSuccessful();
});

it('creates a product with attached modifier groups', function () {
    $category = Category::factory()->create();
    $groups = ModifierGroup::factory()->count(2)->create();

    livewire(CreateProduct::class)
        ->fillForm([
            'category_id' => $category->id,
            'name' => 'Латте',
            'slug' => 'latte',
            'description' => 'Кофейный напиток с молоком',
            'price' => 250,
            'sort_order' => 0,
            'is_active' => true,
            'in_stop_list' => false,
            'modifierGroups' => $groups->pluck('id')->toArray(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::where('slug', 'latte')->first();

    expect($product)->not->toBeNull()
        ->and((float) $product->price)->toBe(250.00)
        ->and($product->modifierGroups()->count())->toBe(2);
});

it('toggles in_stop_list when editing a product', function () {
    $product = Product::factory()->create(['in_stop_list' => false]);

    livewire(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['in_stop_list' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->refresh()->in_stop_list)->toBeTrue();
});

it('renders the modifier group list page', function () {
    ModifierGroup::factory()->count(2)->create();

    $this->get(ModifierGroupResource::getUrl('index'))
        ->assertSuccessful();
});

it('lists modifiers for a group via the relation manager', function () {
    $group = ModifierGroup::factory()->create();
    Modifier::factory()->for($group, 'modifierGroup')->create(['name' => '30 см', 'price_delta' => 150]);

    livewire(ModifiersRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => EditModifierGroup::class,
    ])
        ->assertCanSeeTableRecords($group->modifiers);
});

it('product belongs to category and pivots to modifier groups', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $group = ModifierGroup::factory()->create();
    Modifier::factory()->for($group, 'modifierGroup')->create();
    $product->modifierGroups()->attach($group, ['sort_order' => 0]);

    expect($product->category->is($category))->toBeTrue()
        ->and($product->modifierGroups()->count())->toBe(1)
        ->and($group->modifiers()->count())->toBe(1);
});
