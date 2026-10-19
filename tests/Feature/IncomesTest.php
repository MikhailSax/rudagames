<?php

use App\Livewire\Admin\AnalyticsDashboard;
use App\Livewire\Admin\GamesCrud;
use App\Livewire\Admin\GameShow;
use App\Livewire\Admin\IncomesCrud;
use App\Models\Game;
use App\Models\GameCategory;
use App\Models\GameParticipation;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Services\Reports\WeeklyReportService;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function incomesGame(string $playedAt = '2026-10-13 19:00:00'): Game
{
    $product = Product::firstOrCreate(['name' => 'Мозгобойня']);
    $category = GameCategory::firstOrCreate(['product_id' => $product->id, 'name' => 'Легкая']);

    return Game::create([
        'product_id' => $product->id, 'category_id' => $category->id,
        'name' => 'Легкая #35', 'played_at' => $playedAt,
    ]);
}

function incomesParticipation(Game $game, float $revenue): void
{
    $team = Team::create(['phone' => '7900'.random_int(1000000, 9999999), 'current_name' => 'Команда '.random_int(1, 99999)]);
    GameParticipation::create([
        'game_id' => $game->id, 'team_id' => $team->id,
        'team_name_at_time' => $team->current_name, 'players_count' => 5, 'revenue' => $revenue,
    ]);
}

test('incomes page: subcategories in both categories, an unlinked income', function () {
    $component = Livewire::actingAs(User::factory()->create())
        ->test(IncomesCrud::class, ['month' => '2026-10']);

    foreach ([IncomeCategory::GROUP_GAME => 'Бар', IncomeCategory::GROUP_OTHER => 'Выездные мероприятия'] as $group => $name) {
        $component->call('createCategory', $group)->set('categoryName', $name)->call('saveCategory')->assertHasNoErrors();
    }

    $component->call('createIncome', IncomeCategory::GROUP_OTHER)
        ->set('income_category_id', IncomeCategory::where('name', 'Выездные мероприятия')->first()->id)
        ->set('amount', '25000')
        ->call('saveIncome')
        ->assertHasErrors(['contact_name', 'contact_phone', 'venue'])
        ->set('contact_name', 'Иван Петров')
        ->set('contact_phone', 'abc')
        ->set('venue', 'Офис заказчика')
        ->set('held_at', '2026-10-14T18:30')
        ->call('saveIncome')
        ->assertHasErrors('contact_phone')
        ->set('contact_phone', '+7 (902) 111-22-33')
        ->call('saveIncome')
        ->assertHasNoErrors()
        ->assertSee('Выездные мероприятия')
        ->assertSee('Билеты');

    // Имя, телефон и место видны в таблице
    $component->assertSee('Иван Петров')->assertSee('+7 (902) 111-22-33')->assertSee('Офис заказчика');

    $income = Income::first();
    expect($income->game_id)->toBeNull();
    expect($income->contact_name)->toBe('Иван Петров');
    expect($income->contact_phone)->toBe('+7 (902) 111-22-33');
    expect($income->venue)->toBe('Офис заказчика');
    expect($income->held_at->format('Y-m-d H:i'))->toBe('2026-10-14 18:30');
    expect($income->received_on->toDateString())->toBe('2026-10-14');
});

test('an unlinked income can be dated in the past and comment is optional', function () {
    $category = IncomeCategory::create(['group' => IncomeCategory::GROUP_OTHER, 'name' => 'Спонсорство']);

    Livewire::actingAs(User::factory()->create())
        ->test(IncomesCrud::class)
        ->call('createIncome', IncomeCategory::GROUP_OTHER)
        ->set('income_category_id', $category->id)
        ->set('amount', '10000')
        ->set('contact_name', 'Анна')
        ->set('contact_phone', '89021112233')
        ->set('venue', 'Бар «41»')
        ->set('held_at', '2025-03-01T20:00')
        ->call('saveIncome')
        ->assertHasNoErrors();

    $income = Income::first();
    expect($income->held_at->format('Y-m-d H:i'))->toBe('2025-03-01 20:00');
    expect($income->comment)->toBeNull();
});

test('game incomes require a game and are added to the game revenue together with tickets', function () {
    $game = incomesGame();
    incomesParticipation($game, 3000);
    incomesParticipation($game, 2000);
    $bar = IncomeCategory::create(['group' => IncomeCategory::GROUP_GAME, 'name' => 'Бар']);

    Livewire::actingAs(User::factory()->create())
        ->test(IncomesCrud::class)
        ->call('createIncome', IncomeCategory::GROUP_GAME)
        ->set('income_category_id', $bar->id)
        ->set('amount', '1500')
        ->call('saveIncome')
        ->assertHasErrors('game_id')
        ->set('game_id', $game->id)
        ->call('saveIncome')
        ->assertHasNoErrors();

    expect((float) $game->fresh()->actual_revenue)->toBe(6500.0); // 5000 билеты + 1500 бар
    // По игре имя и телефон не нужны, время берётся из игры
    expect(Income::first()->held_at->format('Y-m-d H:i'))->toBe('2026-10-13 19:00');
    expect(Income::first()->received_on->toDateString())->toBe('2026-10-13');

    Income::first()->delete();
    expect((float) $game->fresh()->actual_revenue)->toBe(5000.0);
});

test('an income subcategory with incomes cannot be deleted', function () {
    $category = IncomeCategory::create(['group' => IncomeCategory::GROUP_OTHER, 'name' => 'Гранты']);
    Income::create(['income_category_id' => $category->id, 'amount' => 1000, 'received_on' => '2026-10-02']);

    Livewire::actingAs(User::factory()->create())
        ->test(IncomesCrud::class)
        ->call('deleteCategory', $category->id)
        ->assertHasErrors('delete-category');

    expect(IncomeCategory::count())->toBe(1);
});

test('itemized game revenue is not overwritten from the finance form', function () {
    $game = incomesGame();
    incomesParticipation($game, 4000);
    $game->syncRevenueFromItems();

    Livewire::actingAs(User::factory()->create())
        ->test(GameShow::class, ['game' => $game->fresh()])
        ->call('openFinance')
        ->set('actual_revenue', '1')
        ->set('actual_expenses', '500')
        ->call('saveFinance')
        ->assertHasNoErrors();

    $game->refresh();
    expect((float) $game->actual_revenue)->toBe(4000.0);
    expect((float) $game->actual_expenses)->toBe(500.0);
});

test('weekly report: game incomes counted once, unlinked incomes added to profit', function () {
    $game = incomesGame('2026-10-13 19:00:00');
    incomesParticipation($game, 5000);
    $bar = IncomeCategory::create(['group' => IncomeCategory::GROUP_GAME, 'name' => 'Бар']);
    $trip = IncomeCategory::create(['group' => IncomeCategory::GROUP_OTHER, 'name' => 'Выездные мероприятия']);
    Income::create(['income_category_id' => $bar->id, 'game_id' => $game->id, 'amount' => 1000, 'received_on' => '2026-10-13']);
    Income::create(['income_category_id' => $trip->id, 'amount' => 20000, 'received_on' => '2026-10-18']);
    $game->update(['actual_expenses' => 2000]);

    $report = app(WeeklyReportService::class)->generateFor(Carbon::parse('2026-10-12'), Carbon::parse('2026-10-18'));

    expect((float) $report->revenue)->toBe(26000.0); // 5000 билеты + 1000 бар + 20000 выезд
    expect((float) $report->profit)->toBe(24000.0); // (6000 − 2000) + 20000
    expect((float) $report->avg_check)->toBe(5000.0); // только билеты
});

test('games list flags past games without results or expenses', function () {
    incomesGame(now()->subDay()->toDateTimeString());

    Livewire::actingAs(User::factory()->create())
        ->test(GamesCrud::class)
        ->assertSee('итоги не пришли')
        ->assertSee('расходы не сданы');
});

test('analytics month revenue includes incomes', function () {
    $category = IncomeCategory::create(['group' => IncomeCategory::GROUP_OTHER, 'name' => 'Спонсорство']);
    Income::create(['income_category_id' => $category->id, 'amount' => 15000, 'received_on' => now()->startOfMonth()->toDateString()]);

    $component = Livewire::actingAs(User::factory()->create())->test(AnalyticsDashboard::class);

    expect($component->instance()->currentMonthRevenue)->toBe(15000.0);
    expect($component->instance()->revenueByMonth[now()->format('Y-m')])->toBe(15000.0);
});
