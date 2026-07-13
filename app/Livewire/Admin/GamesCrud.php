<?php

namespace App\Livewire\Admin;

use App\Models\Game;
use App\Models\GameCategory;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class GamesCrud extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $search = '';
    public ?int $productFilter = null;
    public string $timeFilter = 'all'; // all | upcoming | past

    public ?int $editingId = null;

    // --- Поля создания/редактирования игры ---
    public ?int $product_id = null;
    public ?int $category_id = null;
    public string $name = '';
    public string $played_at = '';
    public string $venue = '';
    public string $cost = '';

    // --- Поля финансов (Модуль 2, вносятся вручную после игры) ---
    public ?int $financeGameId = null;
    public string $actual_revenue = '';
    public string $actual_expenses = '';

    #[Computed]
    public function games()
    {
        return Game::query()
            ->with(['product', 'category'])
            ->withCount('participations')
            ->withAvg('participations', 'players_count')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->productFilter, fn ($q) => $q->where('product_id', $this->productFilter))
            ->when($this->timeFilter === 'upcoming', fn ($q) => $q->where('played_at', '>', now()))
            ->when($this->timeFilter === 'past', fn ($q) => $q->where('played_at', '<=', now()))
            ->orderByDesc('played_at')
            ->paginate(20);
    }

    #[Computed]
    public function products()
    {
        return Product::orderBy('name')->get();
    }

    #[Computed]
    public function categoriesForSelectedProduct()
    {
        if (! $this->product_id) {
            return collect();
        }

        return GameCategory::where('product_id', $this->product_id)->orderBy('name')->get();
    }

    public function updatedProductId(): void
    {
        // При смене продукта сбрасываем выбранную категорию — она могла принадлежать другому продукту
        $this->category_id = null;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingProductFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTimeFilter(): void
    {
        $this->resetPage();
    }

    // --- CRUD игры ---

    public function create(): void
    {
        $this->resetForm();
        $this->modal('game-form')->show();
    }

    public function edit(int $id): void
    {
        $game = Game::findOrFail($id);

        $this->editingId = $game->id;
        $this->product_id = $game->product_id;
        $this->category_id = $game->category_id;
        $this->name = $game->name;
        $this->played_at = $game->played_at->format('Y-m-d\TH:i');
        $this->venue = $game->venue ?? '';
        $this->cost = $game->cost !== null ? (string) $game->cost : '';

        $this->resetErrorBag();
        $this->modal('game-form')->show();
    }

    public function save(): void
    {
        $this->validate([
            'product_id' => 'required|integer|exists:products,id',
            'category_id' => 'required|integer|exists:game_categories,id',
            'name' => 'required|string|max:255',
            'played_at' => 'required|date',
            'venue' => 'nullable|string|max:255',
            'cost' => 'nullable|numeric|min:0',
        ]);

        $data = [
            'product_id' => $this->product_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'played_at' => Carbon::parse($this->played_at),
            'venue' => $this->venue ?: null,
            'cost' => $this->cost !== '' ? $this->cost : null,
        ];

        if ($this->editingId) {
            Game::findOrFail($this->editingId)->update($data);
        } else {
            Game::create($data);
        }

        $this->modal('game-form')->close();
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Game::findOrFail($id)->delete();
    }

    // --- Модуль 2: ручной ввод финансов после игры ---

    public function openFinance(int $id): void
    {
        $game = Game::findOrFail($id);

        $this->financeGameId = $game->id;
        $this->actual_revenue = $game->actual_revenue !== null ? (string) $game->actual_revenue : '';
        $this->actual_expenses = $game->actual_expenses !== null ? (string) $game->actual_expenses : '';

        $this->resetErrorBag();
        $this->modal('finance-form')->show();
    }

    public function saveFinance(): void
    {
        $this->validate([
            'actual_revenue' => 'required|numeric|min:0',
            'actual_expenses' => 'required|numeric|min:0',
        ]);

        Game::findOrFail($this->financeGameId)->update([
            'actual_revenue' => $this->actual_revenue,
            'actual_expenses' => $this->actual_expenses,
        ]);

        $this->modal('finance-form')->close();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->product_id = null;
        $this->category_id = null;
        $this->name = '';
        $this->played_at = '';
        $this->venue = '';
        $this->cost = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.admin.games-crud')
            ->layoutData(['title' => 'Игры']);
    }
}
