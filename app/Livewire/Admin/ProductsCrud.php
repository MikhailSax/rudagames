<?php

namespace App\Livewire\Admin;

use App\Models\GameCategory;
use App\Models\Product;
use Illuminate\Database\QueryException;
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
            ->with(['categories' => fn ($q) => $q->withCount('games')->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function categoryProductForModal(): ?Product
    {
        return $this->categoryProductId ? Product::find($this->categoryProductId) : null;
    }

    /**
     * Стабильный цветовой акцент на продукт (по id), чтобы список из многих
     * продуктов было проще сканировать взглядом. Классы полностью статичны —
     * так Tailwind находит их при сборке.
     */
    public function productAccent(int $id): array
    {
        return match ($id % 6) {
            0 => ['bg' => 'bg-blue-100 dark:bg-blue-500/15', 'text' => 'text-blue-700 dark:text-blue-300'],
            1 => ['bg' => 'bg-purple-100 dark:bg-purple-500/15', 'text' => 'text-purple-700 dark:text-purple-300'],
            2 => ['bg' => 'bg-teal-100 dark:bg-teal-500/15', 'text' => 'text-teal-700 dark:text-teal-300'],
            3 => ['bg' => 'bg-amber-100 dark:bg-amber-500/15', 'text' => 'text-amber-700 dark:text-amber-300'],
            4 => ['bg' => 'bg-rose-100 dark:bg-rose-500/15', 'text' => 'text-rose-700 dark:text-rose-300'],
            default => ['bg' => 'bg-emerald-100 dark:bg-emerald-500/15', 'text' => 'text-emerald-700 dark:text-emerald-300'],
        };
    }

    public function pluralizeCategories(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        return match (true) {
            $mod10 === 1 && $mod100 !== 11 => 'категория',
            in_array($mod10, [2, 3, 4], true) && ! in_array($mod100, [12, 13, 14], true) => 'категории',
            default => 'категорий',
        };
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
            ->when($this->editingProductId, fn ($q) => $q->where('id', '!=', $this->editingProductId))
            ->exists();

        if ($exists) {
            $this->addError('productName', 'Продукт с таким названием уже существует.');

            return;
        }

        try {
            if ($this->editingProductId) {
                Product::findOrFail($this->editingProductId)->update(['name' => $this->productName]);
            } else {
                Product::create(['name' => $this->productName]);
            }
        } catch (QueryException) {
            // Кто-то создал продукт с таким же именем долями секунды раньше.
            $this->addError('productName', 'Продукт с таким названием уже существует.');

            return;
        }

        $this->modal('product-form')->close();
    }

    public function deleteProduct(int $id): void
    {
        $product = Product::withCount('games')->findOrFail($id);

        if ($product->games_count > 0) {
            $this->addError('delete-product-'.$id, 'Нельзя удалить продукт, у которого есть игры.');

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
            ->when($this->editingCategoryId, fn ($q) => $q->where('id', '!=', $this->editingCategoryId))
            ->exists();

        if ($exists) {
            $this->addError('categoryName', 'Такая категория уже существует у этого продукта.');

            return;
        }

        try {
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
        } catch (QueryException) {
            $this->addError('categoryName', 'Такая категория уже существует у этого продукта.');

            return;
        }

        $this->modal('category-form')->close();
    }

    public function deleteCategory(int $id): void
    {
        $category = GameCategory::withCount('games')->findOrFail($id);

        if ($category->games_count > 0) {
            $this->addError('delete-category-'.$id, 'Нельзя удалить категорию, у которой есть игры.');

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
