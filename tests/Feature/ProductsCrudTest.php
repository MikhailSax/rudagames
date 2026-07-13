<?php

use App\Livewire\Admin\ProductsCrud;
use App\Models\Game;
use App\Models\GameCategory;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

test('products page renders with no products', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(ProductsCrud::class)
        ->assertOk()
        ->assertSee('Продуктов пока нет');
});

test('products page renders with a mix of products with and without categories', function () {
    $withCategories = Product::create(['name' => 'Мозгобойня']);
    GameCategory::create(['product_id' => $withCategories->id, 'name' => 'Классика']);
    Product::create(['name' => 'Квизмашина']);

    Livewire::actingAs(User::factory()->create())
        ->test(ProductsCrud::class)
        ->assertOk()
        ->assertSee('Мозгобойня')
        ->assertSee('Классика')
        ->assertSee('Квизмашина')
        ->assertSee('Категорий пока нет');
});

test('create/edit/delete product and category flows work, including cascade edge cases', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user);

    Livewire::test(ProductsCrud::class)
        ->call('createProduct')
        ->set('productName', 'Мозгобойня')
        ->call('saveProduct')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Мозгобойня')->firstOrFail();

    // Duplicate product name -> friendly validation error, not a crash.
    Livewire::test(ProductsCrud::class)
        ->call('createProduct')
        ->set('productName', 'Мозгобойня')
        ->call('saveProduct')
        ->assertHasErrors('productName');

    Livewire::test(ProductsCrud::class)
        ->call('createCategory', $product->id)
        ->set('categoryName', 'Классика')
        ->call('saveCategory')
        ->assertHasNoErrors();

    $category = GameCategory::where('product_id', $product->id)->firstOrFail();

    // Duplicate category under the same product -> friendly error.
    Livewire::test(ProductsCrud::class)
        ->call('createCategory', $product->id)
        ->set('categoryName', 'Классика')
        ->call('saveCategory')
        ->assertHasErrors('categoryName');

    // A category with games attached cannot be deleted.
    $game = Game::create([
        'product_id' => $product->id,
        'category_id' => $category->id,
        'name' => 'Игра 1',
        'played_at' => now(),
    ]);

    Livewire::test(ProductsCrud::class)->call('deleteCategory', $category->id);
    expect(GameCategory::find($category->id))->not->toBeNull();

    // A product with games (via its category) cannot be deleted either.
    Livewire::test(ProductsCrud::class)->call('deleteProduct', $product->id);
    expect(Product::find($product->id))->not->toBeNull();

    // Once the game is gone, deletion works.
    $game->delete();
    Livewire::test(ProductsCrud::class)->call('deleteCategory', $category->id);
    expect(GameCategory::find($category->id))->toBeNull();

    Livewire::test(ProductsCrud::class)->call('deleteProduct', $product->id);
    expect(Product::find($product->id))->toBeNull();

    // Deleting a product with an EMPTY category (no games) cascades the category too.
    $product2 = Product::create(['name' => 'Квизмашина']);
    $category2 = GameCategory::create(['product_id' => $product2->id, 'name' => 'Пустая категория']);

    Livewire::test(ProductsCrud::class)->call('deleteProduct', $product2->id);
    expect(Product::find($product2->id))->toBeNull();
    expect(GameCategory::find($category2->id))->toBeNull();
});

test('pluralizeCategories handles Russian plural rules correctly', function () {
    $component = new ProductsCrud;

    expect($component->pluralizeCategories(1))->toBe('категория');
    expect($component->pluralizeCategories(2))->toBe('категории');
    expect($component->pluralizeCategories(5))->toBe('категорий');
    expect($component->pluralizeCategories(11))->toBe('категорий');
    expect($component->pluralizeCategories(21))->toBe('категория');
    expect($component->pluralizeCategories(0))->toBe('категорий');
});
