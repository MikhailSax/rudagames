<?php

namespace App\Livewire\Admin;

use App\Models\Game;
use App\Models\GameCategory;
use App\Models\GameParticipation;
use App\Models\Product;
use App\Models\RudaRegistration;
use App\Models\Team;
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

    // --- Добавление команды в игру ---
    public string $teamSearch = '';

    public ?int $addTeamId = null;

    public string $addPlayersCount = '';

    public string $addRevenue = '';

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

    // Заявки из вебхука Руды teamRegistered — до итогов игры это единственный
    // список записавшихся команд (участия появляются только после statisticsSubmitted)
    #[Computed]
    public function registrations()
    {
        return RudaRegistration::with('team')
            ->where('game_id', $this->game->id)
            ->orderBy('created_at')
            ->get();
    }

    // Доходы сверх билетов (страница «Доходы», категория «по играм»)
    #[Computed]
    public function gameIncomes()
    {
        return $this->game->incomes()->with('category')->orderBy('id')->get();
    }

    // Выручка считается сама, если есть билеты из Руды или доходы по игре
    #[Computed]
    public function revenueItemized(): bool
    {
        return $this->game->hasItemizedRevenue();
    }

    // Расходы по статьям (страница «Расходы», группа «по играм»)
    #[Computed]
    public function gameExpenses()
    {
        return $this->game->expenses()->with('category')->orderBy('id')->get();
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
        // Выручка и расходы, внесённые по статьям, считаются сами — вручную меняем только остальное
        $expensesItemized = $this->gameExpenses->isNotEmpty();
        $revenueItemized = $this->revenueItemized;

        $this->validate([
            'actual_revenue' => $revenueItemized ? 'nullable' : 'required|numeric|min:0',
            'actual_expenses' => $expensesItemized ? 'nullable' : 'required|numeric|min:0',
        ]);

        $this->game->update(array_merge(
            $revenueItemized ? [] : ['actual_revenue' => $this->actual_revenue],
            $expensesItemized ? [] : ['actual_expenses' => $this->actual_expenses],
        ));

        $this->game->refresh();
        $this->modal('finance-form')->close();
    }

    // --- Состав игры: добавить / удалить команду ---

    // Команды для выбора: поиск по названию, капитану или телефону; уже участвующие не показываем
    #[Computed]
    public function teamOptions()
    {
        $search = mb_strtolower(trim($this->teamSearch));
        $digits = preg_replace('/\D/', '', $search);

        // Фильтруем в PHP: LIKE в SQLite не умеет без учёта регистра для кириллицы
        return Team::query()
            ->whereNotIn('id', $this->participations->pluck('team_id'))
            ->orderByDesc('last_game_at')
            ->orderBy('current_name')
            ->get(['id', 'current_name', 'captain_name', 'phone'])
            ->when($search !== '', fn ($teams) => $teams->filter(fn (Team $team) => str_contains(mb_strtolower($team->current_name), $search)
                || str_contains(mb_strtolower((string) $team->captain_name), $search)
                || ($digits !== '' && str_contains((string) $team->phone, $digits))))
            ->take(30)
            ->values();
    }

    public function openAddTeam(): void
    {
        $this->teamSearch = '';
        $this->addTeamId = null;
        $this->addPlayersCount = '';
        $this->addRevenue = '';
        $this->resetErrorBag();
        $this->modal('add-team')->show();
    }

    public function selectTeam(int $teamId): void
    {
        $this->addTeamId = $teamId;
    }

    // Выручка по умолчанию = игроки × цена билета игры
    public function updatedAddPlayersCount(): void
    {
        if ($this->game->cost !== null && is_numeric($this->addPlayersCount)) {
            $this->addRevenue = (string) ((int) $this->addPlayersCount * (float) $this->game->cost);
        }
    }

    public function addTeam(): void
    {
        $this->validate([
            'addTeamId' => 'required|integer|exists:teams,id',
            'addPlayersCount' => 'required|integer|min:0|max:255',
            'addRevenue' => 'required|numeric|min:0',
        ], [], [
            'addTeamId' => 'команда',
            'addPlayersCount' => 'игроков',
            'addRevenue' => 'выручка',
        ]);

        if (GameParticipation::where('game_id', $this->game->id)->where('team_id', $this->addTeamId)->exists()) {
            $this->addError('addTeamId', 'Эта команда уже есть в игре.');

            return;
        }

        $team = Team::findOrFail($this->addTeamId);

        GameParticipation::create([
            'game_id' => $this->game->id,
            'team_id' => $team->id,
            'team_name_at_time' => $team->current_name,
            'players_count' => (int) $this->addPlayersCount,
            'revenue' => $this->addRevenue,
        ]);

        $this->afterRosterChange();
        $this->modal('add-team')->close();
    }

    public function removeParticipation(int $participationId): void
    {
        GameParticipation::where('game_id', $this->game->id)->whereKey($participationId)->delete();

        $this->afterRosterChange();
    }

    // Выручка игры считается по билетам — пересчитываем после изменения состава
    private function afterRosterChange(): void
    {
        if ($this->game->hasItemizedRevenue()) {
            $this->game->syncRevenueFromItems();
        } else {
            // Убрали последнюю команду: билетов больше нет, а выручка была посчитана по ним
            $this->game->update(['actual_revenue' => null]);
        }

        $this->game->refresh();
        unset($this->participations, $this->teamOptions, $this->revenueItemized);
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
