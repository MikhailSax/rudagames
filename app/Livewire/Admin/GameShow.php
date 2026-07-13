<?php

namespace App\Livewire\Admin;

use App\Models\Game;
use App\Models\GameCategory;
use App\Models\GameParticipation;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GameShow extends Component
{
    public Game $game;

    // --- Редактирование основной информации ---
    public ?int $product_id = null;
    public ?int $category_id = null;
    public string $name = '';
    public string $played_at = '';
    public string $venue = '';
    public string $cost = '';

    // --- Финансы (Модуль 2, вносятся вручную после игры) ---
    public string $actual_revenue = '';
    public string $actual_expenses = '';

    public function mount(): void
    {
        $this->game->load(['product', 'category']);
        $this->fillEditForm();
        $this->fillFinanceForm();
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

    #[Computed]
    public function participations()
    {
        return GameParticipation::with('team')
            ->where('game_id', $this->game->id)
            ->orderByDesc('revenue')
            ->get();
    }

    #[Computed]
    public function avgCheck(): ?float
    {
        if ($this->game->actual_revenue === null) {
            return null;
        }

        $count = $this->participations->count();

        return $count > 0 ? round((float) $this->game->actual_revenue / $count, 2) : null;
    }

    #[Computed]
    public function avgTeamSize(): ?float
    {
        $avg = $this->participations->avg('players_count');

        return $avg !== null ? round((float) $avg, 2) : null;
    }

    public function updatedProductId(): void
    {
        // При смене продукта сбрасываем выбранную категорию — она могла принадлежать другому продукту
        $this->category_id = null;
    }

    // --- Редактирование основной информации ---

    public function openEdit(): void
    {
        $this->fillEditForm();
        $this->resetErrorBag();
        $this->modal('edit-game')->show();
    }

    public function saveEdit(): void
    {
        $this->validate([
            'product_id' => 'required|integer|exists:products,id',
            'category_id' => 'required|integer|exists:game_categories,id',
            'name' => 'required|string|max:255',
            'played_at' => 'required|date',
            'venue' => 'nullable|string|max:255',
            'cost' => 'nullable|numeric|min:0',
        ]);

        $this->game->update([
            'product_id' => $this->product_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'played_at' => Carbon::parse($this->played_at),
            'venue' => $this->venue ?: null,
            'cost' => $this->cost !== '' ? $this->cost : null,
        ]);

        $this->game->refresh()->load(['product', 'category']);
        $this->modal('edit-game')->close();
    }

    // --- Модуль 2: ручной ввод финансов после игры ---

    public function openFinance(): void
    {
        $this->fillFinanceForm();
        $this->resetErrorBag();
        $this->modal('finance-form')->show();
    }

    public function saveFinance(): void
    {
        $this->validate([
            'actual_revenue' => 'required|numeric|min:0',
            'actual_expenses' => 'required|numeric|min:0',
        ]);

        $this->game->update([
            'actual_revenue' => $this->actual_revenue,
            'actual_expenses' => $this->actual_expenses,
        ]);

        $this->game->refresh();
        $this->modal('finance-form')->close();
    }

    public function delete()
    {
        $this->game->delete();

        return $this->redirect(route('admin.games'), navigate: true);
    }

    private function fillEditForm(): void
    {
        $this->product_id = $this->game->product_id;
        $this->category_id = $this->game->category_id;
        $this->name = $this->game->name;
        $this->played_at = $this->game->played_at->format('Y-m-d\TH:i');
        $this->venue = $this->game->venue ?? '';
        $this->cost = $this->game->cost !== null ? (string) $this->game->cost : '';
    }

    private function fillFinanceForm(): void
    {
        $this->actual_revenue = $this->game->actual_revenue !== null ? (string) $this->game->actual_revenue : '';
        $this->actual_expenses = $this->game->actual_expenses !== null ? (string) $this->game->actual_expenses : '';
    }

    public function render()
    {
        return view('livewire.admin.game-show')
            ->layoutData(['title' => $this->game->name]);
    }
}
