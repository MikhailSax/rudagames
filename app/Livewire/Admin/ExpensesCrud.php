<?php

namespace App\Livewire\Admin;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Game;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Учёт расходов по трём группам. Внутри каждой группы руководитель сам заводит
 * категории. Расходы группы «по играм» привязываются к игре и автоматически
 * складываются в её расходы (Game::syncExpensesFromItems).
 */
#[Layout('layouts.app')]
class ExpensesCrud extends Component
{
    #[Url]
    public string $month = '';

    #[Url(as: 'group')]
    public string $groupFilter = '';

    // Открыть форму расхода сразу для игры (ссылка со страницы игры)
    #[Url(as: 'game')]
    public ?int $openForGame = null;

    // --- Категория ---
    public ?int $editingCategoryId = null;

    public string $categoryGroup = '';

    public string $categoryName = '';

    // --- Расход ---
    public ?int $editingExpenseId = null;

    public string $expenseGroup = ExpenseCategory::GROUP_VARIABLE;

    public ?int $expense_category_id = null;

    public ?int $game_id = null;

    public string $amount = '';

    public string $spent_on = '';

    public string $comment = '';

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = now()->format('Y-m');
        }

        if ($this->openForGame && $game = Game::find($this->openForGame)) {
            $this->month = $game->played_at->format('Y-m');
            $this->createExpense(ExpenseCategory::GROUP_GAME, $game->id);
        }
    }

    private function monthStart(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->month.'-01')->startOfDay();
    }

    /** @return array<string, string> */
    #[Computed]
    public function groups(): array
    {
        return ExpenseCategory::GROUPS;
    }

    #[Computed]
    public function categories()
    {
        $start = $this->monthStart();

        return ExpenseCategory::withCount('expenses')
            ->withSum(['expenses as month_total' => fn ($q) => $q->whereBetween('spent_on', [$start, $start->copy()->endOfMonth()])], 'amount')
            ->orderBy('name')
            ->get()
            ->groupBy('group');
    }

    #[Computed]
    public function expenses()
    {
        $start = $this->monthStart();

        return Expense::with(['category', 'game'])
            ->whereBetween('spent_on', [$start, $start->copy()->endOfMonth()])
            ->when($this->groupFilter !== '', fn ($q) => $q->whereHas('category', fn ($c) => $c->where('group', $this->groupFilter)))
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function monthTotal(): float
    {
        return (float) $this->categories->flatten()->sum('month_total');
    }

    #[Computed]
    public function categoriesForExpenseGroup()
    {
        return ExpenseCategory::where('group', $this->expenseGroup)->orderBy('name')->get();
    }

    // Игры для привязки: месяц выбранной даты (или текущий месяц страницы) ± соседние
    #[Computed]
    public function gamesForSelect()
    {
        $start = $this->monthStart()->subMonth();

        return Game::whereBetween('played_at', [$start, $start->copy()->addMonths(3)])
            ->when($this->game_id, fn ($q) => $q->orWhere('id', $this->game_id))
            ->orderByDesc('played_at')
            ->get(['id', 'name', 'played_at', 'venue']);
    }

    public function updatedExpenseGroup(): void
    {
        $this->expense_category_id = null;

        if ($this->expenseGroup !== ExpenseCategory::GROUP_GAME) {
            $this->game_id = null;
        }
    }

    // --- Категории ---

    public function createCategory(string $group): void
    {
        $this->editingCategoryId = null;
        $this->categoryGroup = $group;
        $this->categoryName = '';
        $this->resetErrorBag();
        $this->modal('category-form')->show();
    }

    public function editCategory(int $id): void
    {
        $category = ExpenseCategory::findOrFail($id);
        $this->editingCategoryId = $category->id;
        $this->categoryGroup = $category->group;
        $this->categoryName = $category->name;
        $this->resetErrorBag();
        $this->modal('category-form')->show();
    }

    public function saveCategory(): void
    {
        $this->validate([
            'categoryGroup' => ['required', Rule::in(array_keys(ExpenseCategory::GROUPS))],
            'categoryName' => 'required|string|max:255',
        ], [], ['categoryName' => 'название']);

        $exists = ExpenseCategory::where('group', $this->categoryGroup)
            ->where('name', $this->categoryName)
            ->when($this->editingCategoryId, fn ($q) => $q->where('id', '!=', $this->editingCategoryId))
            ->exists();

        if ($exists) {
            $this->addError('categoryName', 'Такая подкатегория в этой категории уже есть.');

            return;
        }

        try {
            if ($this->editingCategoryId) {
                ExpenseCategory::findOrFail($this->editingCategoryId)->update(['name' => $this->categoryName]);
            } else {
                ExpenseCategory::create(['group' => $this->categoryGroup, 'name' => $this->categoryName]);
            }
        } catch (QueryException) {
            $this->addError('categoryName', 'Такая подкатегория в этой категории уже есть.');

            return;
        }

        $this->modal('category-form')->close();
    }

    public function deleteCategory(int $id): void
    {
        $category = ExpenseCategory::withCount('expenses')->findOrFail($id);

        if ($category->expenses_count > 0) {
            $this->addError('delete-category', "Нельзя удалить «{$category->name}»: по ней есть расходы ({$category->expenses_count}).");

            return;
        }

        $category->delete();
    }

    // --- Расходы ---

    public function createExpense(?string $group = null, ?int $gameId = null): void
    {
        $this->editingExpenseId = null;
        $this->expenseGroup = $group ?? ($this->groupFilter ?: ExpenseCategory::GROUP_VARIABLE);
        $this->expense_category_id = null;
        $this->game_id = $gameId;
        $this->amount = '';
        $this->spent_on = now()->format('Y-m') === $this->month ? now()->format('Y-m-d') : $this->month.'-01';
        $this->comment = '';
        $this->resetErrorBag();
        $this->modal('expense-form')->show();
    }

    public function editExpense(int $id): void
    {
        $expense = Expense::with('category')->findOrFail($id);
        $this->editingExpenseId = $expense->id;
        $this->expenseGroup = $expense->category->group;
        $this->expense_category_id = $expense->expense_category_id;
        $this->game_id = $expense->game_id;
        $this->amount = (string) $expense->amount;
        $this->spent_on = $expense->spent_on->format('Y-m-d');
        $this->comment = $expense->comment ?? '';
        $this->resetErrorBag();
        $this->modal('expense-form')->show();
    }

    public function saveExpense(): void
    {
        $forGame = $this->expenseGroup === ExpenseCategory::GROUP_GAME;

        $this->validate([
            'expenseGroup' => ['required', Rule::in(array_keys(ExpenseCategory::GROUPS))],
            'expense_category_id' => ['required', Rule::exists('expense_categories', 'id')->where('group', $this->expenseGroup)],
            'game_id' => [$forGame ? 'required' : 'nullable', 'integer', 'exists:games,id'],
            'amount' => 'required|numeric|min:0.01',
            'spent_on' => [$forGame ? 'nullable' : 'required', 'date'],
            'comment' => 'nullable|string|max:255',
        ], [], [
            'expense_category_id' => 'подкатегория',
            'game_id' => 'игра',
            'amount' => 'сумма',
            'spent_on' => 'дата',
        ]);

        // Расход по игре датируется днём игры — так он попадает в нужный месяц и неделю
        $spentOn = $forGame
            ? Game::findOrFail($this->game_id)->played_at->toDateString()
            : $this->spent_on;

        $data = [
            'expense_category_id' => $this->expense_category_id,
            'game_id' => $forGame ? $this->game_id : null,
            'amount' => $this->amount,
            'spent_on' => $spentOn,
            'comment' => $this->comment ?: null,
        ];

        if ($this->editingExpenseId) {
            Expense::findOrFail($this->editingExpenseId)->update($data);
        } else {
            Expense::create($data);
        }

        $this->modal('expense-form')->close();
    }

    public function deleteExpense(int $id): void
    {
        Expense::findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.expenses-crud')
            ->layoutData(['title' => 'Расходы']);
    }
}
