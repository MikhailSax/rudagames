<?php

use App\Livewire\Admin\AnalyticsDashboard;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Game;
use App\Models\GameCategory;
use App\Models\GameParticipation;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Services\Reports\FinanceSummaryService;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function financeGame(string $playedAt, float $tickets, ?float $manualExpenses = null): Game
{
    $product = Product::firstOrCreate(['name' => 'Мозгобойня']);
    $category = GameCategory::firstOrCreate(['product_id' => $product->id, 'name' => 'Легкая']);
    $game = Game::create([
        'product_id' => $product->id, 'category_id' => $category->id,
        'name' => 'Игра '.$playedAt, 'played_at' => $playedAt, 'actual_expenses' => $manualExpenses,
    ]);
    $team = Team::create(['phone' => '7900'.random_int(1000000, 9999999), 'current_name' => 'Команда '.random_int(1, 99999)]);
    GameParticipation::create([
        'game_id' => $game->id, 'team_id' => $team->id,
        'team_name_at_time' => $team->current_name, 'players_count' => 5, 'revenue' => $tickets,
    ]);

    return $game;
}

test('finance summary adds incomes and expenses of the period by categories', function () {
    $game = financeGame('2026-10-13 19:00:00', 5000);
    financeGame('2026-10-20 19:00:00', 3000, manualExpenses: 1200); // расходы одной суммой, без статей
    financeGame('2026-11-02 19:00:00', 9999); // вне периода

    $bar = IncomeCategory::create(['group' => IncomeCategory::GROUP_GAME, 'name' => 'Бар']);
    $trip = IncomeCategory::create(['group' => IncomeCategory::GROUP_OTHER, 'name' => 'Выездные мероприятия']);
    Income::create(['income_category_id' => $bar->id, 'game_id' => $game->id, 'amount' => 1000, 'received_on' => '2026-10-13']);
    Income::create(['income_category_id' => $trip->id, 'amount' => 20000, 'received_on' => '2026-10-31']); // последний день периода

    $host = ExpenseCategory::create(['group' => ExpenseCategory::GROUP_GAME, 'name' => 'Ведущий']);
    $server = ExpenseCategory::create(['group' => ExpenseCategory::GROUP_FIXED, 'name' => 'Аренда сервера']);
    $ads = ExpenseCategory::create(['group' => ExpenseCategory::GROUP_VARIABLE, 'name' => 'Продвижение']);
    Expense::create(['expense_category_id' => $host->id, 'game_id' => $game->id, 'amount' => 2500, 'spent_on' => '2026-10-13']);
    Expense::create(['expense_category_id' => $server->id, 'amount' => 800, 'spent_on' => '2026-10-01']);
    Expense::create(['expense_category_id' => $ads->id, 'amount' => 3000, 'spent_on' => '2026-10-15']);
    Expense::create(['expense_category_id' => $ads->id, 'amount' => 7777, 'spent_on' => '2026-11-01']); // вне периода

    $summary = app(FinanceSummaryService::class)->summarize(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

    // Доходы: билеты 8000 + бар 1000 + выезд 20000
    expect($summary['income_total'])->toBe(29000.0);
    expect($summary['income_groups'][IncomeCategory::GROUP_GAME]['total'])->toBe(9000.0);
    expect($summary['income_groups'][IncomeCategory::GROUP_OTHER]['total'])->toBe(20000.0);

    // Расходы: ведущий 2500 + без статей 1200 + сервер 800 + продвижение 3000
    expect($summary['expense_total'])->toBe(7500.0);
    expect($summary['expense_groups'][ExpenseCategory::GROUP_GAME]['total'])->toBe(3700.0);
    expect($summary['expense_groups'][ExpenseCategory::GROUP_FIXED]['total'])->toBe(800.0);
    expect($summary['expense_groups'][ExpenseCategory::GROUP_VARIABLE]['total'])->toBe(3000.0);

    expect($summary['profit'])->toBe(21500.0);
});

test('analytics page shows income and expenses for the chosen dates', function () {
    financeGame('2026-10-13 19:00:00', 5000);
    $ads = ExpenseCategory::create(['group' => ExpenseCategory::GROUP_VARIABLE, 'name' => 'Продвижение']);
    Expense::create(['expense_category_id' => $ads->id, 'amount' => 1500, 'spent_on' => '2026-10-15']);

    $component = Livewire::actingAs(User::factory()->create())
        ->test(AnalyticsDashboard::class)
        ->set('financeFrom', '2026-10-01')
        ->set('financeTo', '2026-10-31')
        ->assertSee('Доходы и расходы')
        ->assertSee('Билеты (из Руды)')
        ->assertSee('Продвижение');

    expect($component->instance()->finance['profit'])->toBe(3500.0);

    // Интервал без данных
    $component->set('financeFrom', '2025-01-01')->set('financeTo', '2025-01-31');
    expect($component->instance()->finance['income_total'])->toBe(0.0);
});
