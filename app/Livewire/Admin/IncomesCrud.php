<?php

namespace App\Livewire\Admin;

use App\Models\Game;
use App\Models\GameParticipation;
use App\Models\Income;
use App\Models\IncomeCategory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Доходы в два уровня: категория (IncomeCategory::GROUPS) и подкатегории,
 * которые руководитель заводит сам. Билеты по регулярным играм приходят из Руды
 * автоматически; здесь вносится всё остальное. Доходы по игре складываются
 * в её выручку (Game::syncRevenueFromItems).
 */
#[Layout('layouts.app')]
class IncomesCrud extends Component
{
    #[Url]
    public string $month = '';

    #[Url(as: 'group')]
    public string $groupFilter = '';

    // Открыть форму дохода сразу для игры (ссылка со страницы игры)
    #[Url(as: 'game')]
    public ?int $openForGame = null;

    // --- Подкатегория ---
    public ?int $editingCategoryId = null;

    public string $categoryGroup = '';

    public string $categoryName = '';

    // --- Доход ---
    public ?int $editingIncomeId = null;

    public string $incomeGroup = IncomeCategory::GROUP_OTHER;

    public ?int $income_category_id = null;

    public ?int $game_id = null;

    public string $amount = '';

    // Дата и время проведения (datetime-local). Можно ставить задним числом.
    public string $held_at = '';

    public string $contact_name = '';

    public string $contact_phone = '';

    public string $venue = '';

    public bool $is_prepaid = false;

    public bool $is_completed = false;

    public string $comment = '';

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = now()->format('Y-m');
        }

        if ($this->openForGame && $game = Game::find($this->openForGame)) {
            $this->month = $game->played_at->format('Y-m');
            $this->createIncome(IncomeCategory::GROUP_GAME, $game->id);
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
        return IncomeCategory::GROUPS;
    }

    #[Computed]
    public function categories()
    {
        $start = $this->monthStart();

        return IncomeCategory::withCount('incomes')
            ->withSum(['incomes as month_total' => fn ($q) => $q->whereBetween('received_on', [$start, $start->copy()->endOfMonth()])], 'amount')
            ->orderBy('name')
            ->get()
            ->groupBy('group');
    }

    // Билеты за месяц: выручка команд из итогов Руды
    #[Computed]
    public function ticketsTotal(): float
    {
        $start = $this->monthStart();

        return (float) GameParticipation::query()
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->whereBetween('games.played_at', [$start, $start->copy()->endOfMonth()])
            ->sum('game_participations.revenue');
    }

    #[Computed]
    public function incomes()
    {
        $start = $this->monthStart();

        return Income::with(['category', 'game'])
            ->whereBetween('received_on', [$start, $start->copy()->endOfMonth()])
            ->when($this->groupFilter !== '', fn ($q) => $q->whereHas('category', fn ($c) => $c->where('group', $this->groupFilter)))
            ->orderByDesc('received_on')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function monthTotal(): float
    {
        return (float) $this->categories->flatten()->sum('month_total') + $this->ticketsTotal;
    }

    #[Computed]
    public function categoriesForIncomeGroup()
    {
        return IncomeCategory::where('group', $this->incomeGroup)->orderBy('name')->get();
    }

    #[Computed]
    public function gamesForSelect()
    {
        $start = $this->monthStart()->subMonth();

        return Game::whereBetween('played_at', [$start, $start->copy()->addMonths(3)])
            ->when($this->game_id, fn ($q) => $q->orWhere('id', $this->game_id))
            ->orderByDesc('played_at')
            ->get(['id', 'name', 'played_at', 'venue']);
    }

    public function updatedIncomeGroup(): void
    {
        $this->income_category_id = null;

        if ($this->incomeGroup !== IncomeCategory::GROUP_GAME) {
            $this->game_id = null;
        }
    }

    // --- Подкатегории ---

    public function createCategory(string $group): void
    {
        $this->editingCategoryId = null;
        $this->categoryGroup = $group;
        $this->categoryName = '';
        $this->resetErrorBag();
        $this->modal('income-category-form')->show();
    }

    public function editCategory(int $id): void
    {
        $category = IncomeCategory::findOrFail($id);
        $this->editingCategoryId = $category->id;
        $this->categoryGroup = $category->group;
        $this->categoryName = $category->name;
        $this->resetErrorBag();
        $this->modal('income-category-form')->show();
    }

    public function saveCategory(): void
    {
        $this->validate([
            'categoryGroup' => ['required', Rule::in(array_keys(IncomeCategory::GROUPS))],
            'categoryName' => 'required|string|max:255',
        ], [], ['categoryName' => 'название']);

        $exists = IncomeCategory::where('group', $this->categoryGroup)
            ->where('name', $this->categoryName)
            ->when($this->editingCategoryId, fn ($q) => $q->where('id', '!=', $this->editingCategoryId))
            ->exists();

        if ($exists) {
            $this->addError('categoryName', 'Такая подкатегория в этой категории уже есть.');

            return;
        }

        try {
            if ($this->editingCategoryId) {
                IncomeCategory::findOrFail($this->editingCategoryId)->update(['name' => $this->categoryName]);
            } else {
                IncomeCategory::create(['group' => $this->categoryGroup, 'name' => $this->categoryName]);
            }
        } catch (QueryException) {
            $this->addError('categoryName', 'Такая подкатегория в этой категории уже есть.');

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

    public function createIncome(?string $group = null, ?int $gameId = null): void
    {
        $this->editingIncomeId = null;
        $this->incomeGroup = $group ?? ($this->groupFilter ?: IncomeCategory::GROUP_OTHER);
        $this->income_category_id = null;
        $this->game_id = $gameId;
        $this->amount = '';
        $this->held_at = now()->format('Y-m') === $this->month ? now()->format('Y-m-d\TH:i') : $this->month.'-01T19:00';
        $this->contact_name = '';
        $this->contact_phone = '';
        $this->venue = '';
        $this->is_prepaid = false;
        $this->is_completed = false;
        $this->comment = '';
        $this->resetErrorBag();
        $this->modal('income-form')->show();
    }

    public function editIncome(int $id): void
    {
        $income = Income::with('category')->findOrFail($id);
        $this->editingIncomeId = $income->id;
        $this->incomeGroup = $income->category->group;
        $this->income_category_id = $income->income_category_id;
        $this->game_id = $income->game_id;
        $this->amount = (string) $income->amount;
        $this->held_at = ($income->held_at ?? $income->received_on)->format('Y-m-d\TH:i');
        $this->contact_name = $income->contact_name ?? '';
        $this->contact_phone = $income->contact_phone ?? '';
        $this->venue = $income->venue ?? '';
        $this->is_prepaid = $income->is_prepaid;
        $this->is_completed = $income->is_completed;
        $this->comment = $income->comment ?? '';
        $this->resetErrorBag();
        $this->modal('income-form')->show();
    }

    public function saveIncome(): void
    {
        $forGame = $this->incomeGroup === IncomeCategory::GROUP_GAME;

        $this->validate([
            'incomeGroup' => ['required', Rule::in(array_keys(IncomeCategory::GROUPS))],
            'income_category_id' => ['required', Rule::exists('income_categories', 'id')->where('group', $this->incomeGroup)],
            'game_id' => [$forGame ? 'required' : 'nullable', 'integer', 'exists:games,id'],
            'amount' => 'required|numeric|min:0.01',
            // Вне игр: кто заказал, где и когда — обязательно; по игре это берётся из самой игры
            'held_at' => [$forGame ? 'nullable' : 'required', 'date'],
            'contact_name' => [$forGame ? 'nullable' : 'required', 'string', 'max:255'],
            'contact_phone' => [$forGame ? 'nullable' : 'required', 'string', 'max:32', 'regex:/^[\d\s()+\-]*$/', function ($attribute, $value, $fail) {
                if ($value !== '' && strlen(preg_replace('/\D/', '', $value)) < 10) {
                    $fail('Телефон должен содержать не меньше 10 цифр.');
                }
            }],
            'venue' => [$forGame ? 'nullable' : 'required', 'string', 'max:255'],
            'comment' => 'nullable|string|max:255',
        ], [], [
            'income_category_id' => 'подкатегория',
            'game_id' => 'игра',
            'amount' => 'сумма',
            'held_at' => 'дата и время проведения',
            'contact_name' => 'имя',
            'contact_phone' => 'телефон',
            'venue' => 'место проведения',
        ]);

        // Доход по игре датируется временем и местом игры — так он попадает в нужный месяц и неделю
        $game = $forGame ? Game::findOrFail($this->game_id) : null;
        $heldAt = $game ? $game->played_at : Carbon::parse($this->held_at);

        $data = [
            'income_category_id' => $this->income_category_id,
            'game_id' => $game?->id,
            'contact_name' => trim($this->contact_name) ?: null,
            'contact_phone' => trim($this->contact_phone) ?: null,
            'venue' => $game ? ($game->venue ?? (trim($this->venue) ?: null)) : trim($this->venue),
            'held_at' => $heldAt,
            'is_prepaid' => $this->is_prepaid,
            'is_completed' => $this->is_completed,
            'amount' => $this->amount,
            'received_on' => $heldAt->toDateString(),
            'comment' => $this->comment ?: null,
        ];

        if ($this->editingIncomeId) {
            Income::findOrFail($this->editingIncomeId)->update($data);
        } else {
            Income::create($data);
        }

        $this->modal('income-form')->close();
    }

    // Чекбоксы прямо в таблице
    public function togglePrepaid(int $id): void
    {
        $income = Income::findOrFail($id);
        $income->update(['is_prepaid' => ! $income->is_prepaid]);
    }

    public function toggleCompleted(int $id): void
    {
        $income = Income::findOrFail($id);
        $income->update(['is_completed' => ! $income->is_completed]);
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
