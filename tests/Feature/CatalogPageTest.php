<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the catalog page for a guest', function () {
    $this->get('/')->assertSuccessful();
});

it('passes active categories with their products to the catalog page', function () {
    $active = Category::factory()->create(['name' => 'Активная']);
    $inactive = Category::factory()->inactive()->create(['name' => 'Скрытая']);

    Product::factory()->for($active)->count(2)->create(['is_active' => true]);
    Product::factory()->for($active)->inactive()->create(['name' => 'Спрятанное']);
    Product::factory()->for($inactive)->create();

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/index')
            ->has('establishment')
            ->where('establishment.accepting_orders', true)
            ->has('categories', 1)
            ->where('categories.0.name', 'Активная')
            ->has('categories.0.products', 2));
});

it('renders the checkout placeholder', function () {
    $this->get('/checkout')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('catalog/checkout'));
});
