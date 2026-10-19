<?php

use App\Livewire\Admin\FinanceAnalytics;
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

function analyticsGame(string $playedAt, float $tickets): Game
{
    $product = Product::firstOrCreate(['name' => 'Мозгобойня']);
    $category = GameCategory::firstOrCreate(['product_id' => $product->id, 'name' => 'Легкая']);
    $game = Game::create([
        'product_id' => $product->id, 'category_id' => $category->id,
        'name' => 'Игра '.$playedAt, 'played_at' => $playedAt,
    ]);
    $team = Team::create(['phone' => '7900'.random_int(1000000, 9999999), 'current_name' => 'Команда '.random_int(1, 99999)]);
    GameParticipation::create([
        'game_id' => $game->id, 'team_id' => $team->id,
        'team_name_at_time' => $team->current_name, 'players_count' => 5, 'revenue' => $tickets,
    ]);

    return $game;
}

function seedAnalyticsData(): void
{
    $game = analyticsGame('2026-09-13 19:00:00', 6000);
    analyticsGame('2026-10-13 19:00:00', 4000);

    $bar = IncomeCategory::create(['group' => IncomeCategory::GROUP_GAME, 'name' => 'Бар']);
    $trip = IncomeCategory::create(['group' => IncomeCategory::GROUP_OTHER, 'name' => 'Выездные мероприятия']);
    Income::create(['income_category_id' => $bar->id, 'game_id' => $game->id, 'amount' => 1000, 'received_on' => '2026-09-13']);
    Income::create(['income_category_id' => $trip->id, 'amount' => 30000, 'received_on' => '2026-10-05', 'venue' => 'Офис', 'contact_name' => 'Анна']);

    $host = ExpenseCategory::create(['group' => ExpenseCategory::GROUP_GAME, 'name' => 'Ведущий']);
    Expense::create(['expense_category_id' => $host->id, 'game_id' => $game->id, 'amount' => 2000, 'spent_on' => '2026-09-13']);
}

test('15% royalty to Ruda is taken from regular games only, not from off-site events', function () {
    seedAnalyticsData();

    $s = app(FinanceSummaryService::class)->summarize(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-31'));

    // База: билеты 6000 + 4000 + бар 1000 = 11000; выездные 30000 не облагаются
    expect($s['royalty_percent'])->toBe(15.0);
    expect($s['royalty_base'])->toBe(11000.0);
    expect($s['royalty'])->toBe(1650.0);
    expect($s['profit'])->toBe(39000.0); // 41000 − 2000
    expect($s['profit_after_royalty'])->toBe(37350.0);
});

test('royalty percent is configurable', function () {
    seedAnalyticsData();
    config(['services.ruda.royalty_percent' => 10]);

    $s = app(FinanceSummaryService::class)->summarize(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-31'));

    expect($s['royalty'])->toBe(1100.0);
});

test('category detail splits by subcategories and months and lists operations', function () {
    seedAnalyticsData();

    $d = app(FinanceSummaryService::class)->categoryDetail('income', IncomeCategory::GROUP_GAME, Carbon::parse('2026-09-01'), Carbon::parse('2026-10-31'));

    expect($d['total'])->toBe(11000.0);
    expect($d['rows'][0])->toBe(['name' => 'Билеты (из Руды)', 'amount' => 10000.0, 'share' => 90.9]);
    expect($d['months']['2026-09'])->toBe(['Билеты (из Руды)' => 6000.0, 'Бар' => 1000.0]);
    expect($d['months']['2026-10'])->toBe(['Билеты (из Руды)' => 4000.0]);
    expect($d['entries'])->toHaveCount(3);
    expect($d['entries'][0]['date']->format('Y-m-d'))->toBe('2026-10-13'); // новые сверху
});

test('finance analytics page: summary and each category tab render', function () {
    seedAnalyticsData();

    $component = Livewire::actingAs(User::factory()->create())
        ->test(FinanceAnalytics::class)
        ->set('from', '2026-09-01')
        ->set('to', '2026-10-31');

    expect(ltrim($component->html()))->toStartWith('<div');
    $component->assertSee('Руде 15%')->assertSee('1 650 ₽')->assertSee('37 350 ₽');

    foreach (array_keys($component->instance()->tabs()) as $tab) {
        $component->set('tab', $tab)->assertOk();
    }

    $component->set('tab', 'income-other')
        ->assertSee('Выездные мероприятия')
        ->assertSee('Октябрь 2026')
        ->assertSee('30 000 ₽')
        ->set('tab', 'expense-game')
        ->assertSee('Ведущий');
});
