<?php

use App\Livewire\Admin\ExpensesCrud;
use App\Livewire\Admin\GameShow;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Game;
use App\Models\GameCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\Reports\WeeklyReportService;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function expensesGame(string $playedAt = '2026-10-13 19:00:00'): Game
{
    $product = Product::firstOrCreate(['name' => 'Мозгобойня']);
    $category = GameCategory::firstOrCreate(['product_id' => $product->id, 'name' => 'Легкая']);

    return Game::create([
        'product_id' => $product->id, 'category_id' => $category->id,
        'name' => 'Легкая #35', 'played_at' => $playedAt, 'actual_revenue' => 10000,
    ]);
}

test('expenses page renders and categories can be created in each group', function () {
    $component = Livewire::actingAs(User::factory()->create())->test(ExpensesCrud::class);

    foreach (array_keys(ExpenseCategory::GROUPS) as $group) {
        $component->call('createCategory', $group)
            ->set('categoryName', 'Категория '.$group)
            ->call('saveCategory')
            ->assertHasNoErrors();
    }

    expect(ExpenseCategory::count())->toBe(3);
    $component->assertSee('Постоянные, привязанные к играм')->assertSee('Переменные');

    // Дубль в той же группе запрещён
    $component->call('createCategory', ExpenseCategory::GROUP_FIXED)
        ->set('categoryName', 'Категория fixed')
        ->call('saveCategory')
        ->assertHasErrors('categoryName');
});

test('game expenses are summed into the game finances automatically', function () {
    $game = expensesGame();
    $host = ExpenseCategory::create(['group' => ExpenseCategory::GROUP_GAME, 'name' => 'Ведущий']);
    $rent = ExpenseCategory::create(['group' => ExpenseCategory::GROUP_GAME, 'name' => 'Аренда зала']);

    $component = Livewire::actingAs(User::factory()->create())
        ->test(ExpensesCrud::class)
        ->call('createExpense', ExpenseCategory::GROUP_GAME, $game->id)
        ->set('expense_category_id', $host->id)
        ->set('amount', '2500')
        ->call('saveExpense')
        ->assertHasNoErrors()
        ->call('createExpense', ExpenseCategory::GROUP_GAME, $game->id)
        ->set('expense_category_id', $rent->id)
        ->set('amount', '1500')
        ->call('saveExpense')
        ->assertHasNoErrors();

    $game->refresh();
    expect((float) $game->actual_expenses)->toBe(4000.0);
    expect($game->profit)->toBe(6000.0);
    // Расход по игре датируется днём игры
    expect(Expense::first()->spent_on->toDateString())->toBe('2026-10-13');

    $component->call('deleteExpense', Expense::where('expense_category_id', $rent->id)->first()->id);
    expect((float) $game->fresh()->actual_expenses)->toBe(2500.0);
});

test('game expense requires a game, others require a category from the same group', function () {
    $hostCategory = ExpenseCategory::create(['group' => ExpenseCategory::GROUP_GAME, 'name' => 'Ведущий']);
    $fixed = ExpenseCategory::create(['group' => ExpenseCategory::GROUP_FIXED, 'name' => 'Офис']);

    Livewire::actingAs(User::factory()->create())
        ->test(ExpensesCrud::class)
        ->call('createExpense', ExpenseCategory::GROUP_GAME)
        ->set('expense_category_id', $hostCategory->id)
        ->set('amount', '100')
        ->call('saveExpense')
        ->assertHasErrors('game_id')
        ->set('expenseGroup', ExpenseCategory::GROUP_FIXED)
        ->set('expense_category_id', $hostCategory->id) // категория чужой группы
        ->set('amount', '30000')
        ->set('spent_on', '2026-10-01')
        ->call('saveExpense')
        ->assertHasErrors('expense_category_id')
        ->set('expense_category_id', $fixed->id)
        ->call('saveExpense')
        ->assertHasNoErrors();

    expect(Expense::first()->game_id)->toBeNull();
});

test('a category with expenses cannot be deleted', function () {
    $category = ExpenseCategory::create(['group' => ExpenseCategory::GROUP_VARIABLE, 'name' => 'Такси']);
    Expense::create(['expense_category_id' => $category->id, 'amount' => 500, 'spent_on' => '2026-10-02']);

    Livewire::actingAs(User::factory()->create())
        ->test(ExpensesCrud::class)
        ->call('deleteCategory', $category->id)
        ->assertHasErrors('delete-category');

    expect(ExpenseCategory::count())->toBe(1);
});

test('game page shows itemized expenses and does not overwrite their sum', function () {
    $game = expensesGame();
    $host = ExpenseCategory::create(['group' => ExpenseCategory::GROUP_GAME, 'name' => 'Ведущий']);
    Expense::create(['expense_category_id' => $host->id, 'game_id' => $game->id, 'amount' => 3000, 'spent_on' => '2026-10-13']);

    Livewire::actingAs(User::factory()->create())
        ->test(GameShow::class, ['game' => $game->fresh()])
        ->assertSee('Ведущий')
        ->call('openFinance')
        ->set('actual_revenue', '12000')
        ->set('actual_expenses', '1')
        ->call('saveFinance')
        ->assertHasNoErrors();

    $game->refresh();
    expect((float) $game->actual_revenue)->toBe(12000.0);
    expect((float) $game->actual_expenses)->toBe(3000.0);
});

test('weekly profit subtracts fixed and variable expenses of the week', function () {
    $game = expensesGame('2026-10-13 19:00:00');
    $host = ExpenseCategory::create(['group' => ExpenseCategory::GROUP_GAME, 'name' => 'Ведущий']);
    $office = ExpenseCategory::create(['group' => ExpenseCategory::GROUP_FIXED, 'name' => 'Офис']);
    Expense::create(['expense_category_id' => $host->id, 'game_id' => $game->id, 'amount' => 4000, 'spent_on' => '2026-10-13']);
    Expense::create(['expense_category_id' => $office->id, 'amount' => 1000, 'spent_on' => '2026-10-14']);
    Expense::create(['expense_category_id' => $office->id, 'amount' => 9999, 'spent_on' => '2026-10-30']); // другая неделя

    $report = app(WeeklyReportService::class)->generateFor(
        Carbon::parse('2026-10-12')->startOfDay(),
        Carbon::parse('2026-10-18')->endOfDay(),
    );

    // 10 000 выручки − 4 000 расходов игры − 1 000 офис
    expect((float) $report->profit)->toBe(5000.0);
});
