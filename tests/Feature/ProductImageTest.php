<?php

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());
});

it('image_url accessor is null when no image', function () {
    $product = Product::factory()->create(['image_path' => null]);

    expect($product->image_url)->toBeNull();
});

it('image_url accessor returns a public URL when image_path is set', function () {
    $product = Product::factory()->create(['image_path' => 'products/pic.jpg']);

    expect($product->image_url)
        ->toBeString()
        ->toContain('products/pic.jpg');
});

it('uploads a product image through the Filament edit form', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['image_path' => null]);

    $file = File::image('pizza.jpg', 600, 600);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['image_path' => $file])
        ->call('save')
        ->assertHasNoFormErrors();

    $product->refresh();

    expect($product->image_path)->toBeString()->not->toBeNull()
        ->and(Storage::disk('public')->exists($product->image_path))->toBeTrue();
});

it('exposes image_url to the catalog Inertia page', function () {
    $category = Category::factory()->create(['is_active' => true]);
    Product::factory()->for($category)->create([
        'is_active' => true,
        'image_path' => 'products/cat.jpg',
    ]);

    auth()->logout();

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories.0.products.0.image_url'));
});
