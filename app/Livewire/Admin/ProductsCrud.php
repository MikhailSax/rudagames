<?php

namespace App\Livewire\Admin;

use App\Models\GameCategory;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProductsCrud extends Component
{
    // --- Продукты ---
    public ?int $editingProductId = null;
    public string $productName = '';

    // --- Категории ---
    public ?int $editingCategoryId = null;
    public ?int $categoryProductId = null;
    public string $categoryName = '';

    #[Computed]
    public function products()
    {
        return Product::withCount('games')
            ->with(['categories' => fn($q) => $q->withCount('games')->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    // --- CRUD продукта ---

    public function createProduct(): void
    {
        $this->editingProductId = null;
        $this->productName = '';
        $this->resetErrorBag();
        $this->modal('product-form')->show();
    }

    public function editProduct(int $id): void
    {
        $product = Product::findOrFail($id);
        $this->editingProductId = $product->id;
        $this->productName = $product->name;
        $this->resetErrorBag();
        $this->modal('product-form')->show();
    }

    public function saveProduct(): void
    {
        $this->validate([
            'productName' => 'required|string|max:255',
        ], [], ['productName' => 'название']);

        $exists = Product::where('name', $this->productName)
            ->when($this->editingProductId, fn($q) => $q->where('id', '!=', $this->editingProductId))
            ->exists();

        if ($exists) {
            $this->addError('productName', 'Продукт с таким названием уже существует.');
            return;
        }

        if ($this->editingProductId) {
            Product::findOrFail($this->editingProductId)->update(['name' => $this->productName]);
        } else {
            Product::create(['name' => $this->productName]);
        }

        $this->modal('product-form')->close();
    }

    public function deleteProduct(int $id): void
    {
        $product = Product::withCount('games')->findOrFail($id);

        if ($product->games_count > 0) {
            $this->addError('delete-product-' . $id, 'Нельзя удалить продукт, у которого есть игры.');
            return;
        }

        $product->delete();
    }

    // --- CRUD категории ---

    public function createCategory(int $productId): void
    {
        $this->editingCategoryId = null;
        $this->categoryProductId = $productId;
        $this->categoryName = '';
        $this->resetErrorBag();
        $this->modal('category-form')->show();
    }

    public function editCategory(int $id): void
    {
        $category = GameCategory::findOrFail($id);
        $this->editingCategoryId = $category->id;
        $this->categoryProductId = $category->product_id;
        $this->categoryName = $category->name;
        $this->resetErrorBag();
        $this->modal('category-form')->show();
    }

    public function saveCategory(): void
    {
        $this->validate([
            'categoryName' => 'required|string|max:255',
        ], [], ['categoryName' => 'название']);

        $exists = GameCategory::where('product_id', $this->categoryProductId)
            ->where('name', $this->categoryName)
            ->when($this->editingCategoryId, fn($q) => $q->where('id', '!=', $this->editingCategoryId))
            ->exists();

        if ($exists) {
            $this->addError('categoryName', 'Такая категория уже существует у этого продукта.');
            return;
        }

        if ($this->editingCategoryId) {
            GameCategory::findOrFail($this->editingCategoryId)->update([
                'name' => $this->categoryName,
            ]);
        } else {
            GameCategory::create([
                'product_id' => $this->categoryProductId,
                'name' => $this->categoryName,
            ]);
        }

        $this->modal('category-form')->close();
    }

    public function deleteCategory(int $id): void
    {
        $category = GameCategory::withCount('games')->findOrFail($id);

        if ($category->games_count > 0) {
            $this->addError('delete-category-' . $id, 'Нельзя удалить категорию, у которой есть игры.');
            return;
        }

        $category->delete();
    }

    public function render()
    {
        return view('livewire.admin.products-crud')
            ->layoutData(['title' => 'Продукты и категории']);
    }
}
