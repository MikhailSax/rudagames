<?php

namespace App\Livewire\Admin;

use App\Models\Income;
use App\Models\IncomeCategory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Разовые доходы вне регулярных игр из Руды (выездные игры и т.п.).
 * Категории руководитель заводит сам; доходы попадают в выручку аналитики
 * и в недельные отчёты.
 */
#[Layout('layouts.app')]
class IncomesCrud extends Component
{
    #[Url]
    public string $month = '';

    // --- Категория ---
    public ?int $editingCategoryId = null;

    public string $categoryName = '';

    // --- Доход ---
    public ?int $editingIncomeId = null;

    public ?int $income_category_id = null;

    public string $amount = '';

    public string $received_on = '';

    public string $comment = '';

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = now()->format('Y-m');
        }
    }

    private function monthStart(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->month.'-01')->startOfDay();
    }

    #[Computed]
    public function categories()
    {
        $start = $this->monthStart();

        return IncomeCategory::withCount('incomes')
            ->withSum(['incomes as month_total' => fn ($q) => $q->whereBetween('received_on', [$start, $start->copy()->endOfMonth()])], 'amount')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function incomes()
    {
        $start = $this->monthStart();

        return Income::with('category')
            ->whereBetween('received_on', [$start, $start->copy()->endOfMonth()])
            ->orderByDesc('received_on')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function monthTotal(): float
    {
        return (float) $this->incomes->sum('amount');
    }

    // --- Категории ---

    public function createCategory(): void
    {
        $this->editingCategoryId = null;
        $this->categoryName = '';
        $this->resetErrorBag();
        $this->modal('income-category-form')->show();
    }

    public function editCategory(int $id): void
    {
        $category = IncomeCategory::findOrFail($id);
        $this->editingCategoryId = $category->id;
        $this->categoryName = $category->name;
        $this->resetErrorBag();
        $this->modal('income-category-form')->show();
    }

    public function saveCategory(): void
    {
        $this->validate([
            'categoryName' => 'required|string|max:255',
        ], [], ['categoryName' => 'название']);

        $exists = IncomeCategory::where('name', $this->categoryName)
            ->when($this->editingCategoryId, fn ($q) => $q->where('id', '!=', $this->editingCategoryId))
            ->exists();

        if ($exists) {
            $this->addError('categoryName', 'Такая категория уже есть.');

            return;
        }

        try {
            if ($this->editingCategoryId) {
                IncomeCategory::findOrFail($this->editingCategoryId)->update(['name' => $this->categoryName]);
            } else {
                IncomeCategory::create(['name' => $this->categoryName]);
            }
        } catch (QueryException) {
            $this->addError('categoryName', 'Такая категория уже есть.');

            return;
        }

        $this->modal('income-category-form')->close();
    }

    public function deleteCategory(int $id): void
    {
        $category = IncomeCategory::withCount('incomes')->findOrFail($id);

        if ($category->incomes_count > 0) {
            $this->addError('delete-category', "Нельзя удалить «{$category->name}»: по ней есть доходы ({$category->incomes_count}).");

            return;
        }

        $category->delete();
    }

    // --- Доходы ---

    public function createIncome(): void
    {
        $this->editingIncomeId = null;
        $this->income_category_id = null;
        $this->amount = '';
        $this->received_on = now()->format('Y-m') === $this->month ? now()->format('Y-m-d') : $this->month.'-01';
        $this->comment = '';
        $this->resetErrorBag();
        $this->modal('income-form')->show();
    }

    public function editIncome(int $id): void
    {
        $income = Income::findOrFail($id);
        $this->editingIncomeId = $income->id;
        $this->income_category_id = $income->income_category_id;
        $this->amount = (string) $income->amount;
        $this->received_on = $income->received_on->format('Y-m-d');
        $this->comment = $income->comment ?? '';
        $this->resetErrorBag();
        $this->modal('income-form')->show();
    }

    public function saveIncome(): void
    {
        $this->validate([
            'income_category_id' => 'required|integer|exists:income_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'received_on' => 'required|date',
            'comment' => 'nullable|string|max:255',
        ], [], [
            'income_category_id' => 'категория',
            'amount' => 'сумма',
            'received_on' => 'дата',
        ]);

        $data = [
            'income_category_id' => $this->income_category_id,
            'amount' => $this->amount,
            'received_on' => $this->received_on,
            'comment' => $this->comment ?: null,
        ];

        if ($this->editingIncomeId) {
            Income::findOrFail($this->editingIncomeId)->update($data);
        } else {
            Income::create($data);
        }

        $this->modal('income-form')->close();
    }

    public function deleteIncome(int $id): void
    {
        Income::findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.incomes-crud')
            ->layoutData(['title' => 'Доходы']);
    }
}
