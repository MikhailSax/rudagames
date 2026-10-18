<?php

use App\Livewire\Admin\AnalyticsDashboard;
use App\Livewire\Admin\IncomesCrud;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\User;
use App\Services\Reports\WeeklyReportService;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

test('incomes page: categories and incomes can be created', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(IncomesCrud::class, ['month' => '2026-10'])
        ->call('createCategory')
        ->set('categoryName', 'Выездная игра')
        ->call('saveCategory')
        ->assertHasNoErrors()
        ->call('createIncome')
        ->set('income_category_id', IncomeCategory::first()->id)
        ->set('amount', '25000')
        ->set('received_on', '2026-10-14')
        ->set('comment', 'Корпоратив, 40 человек')
        ->call('saveIncome')
        ->assertHasNoErrors()
        ->assertSee('Выездная игра')
        ->assertSee('25 000');

    expect(Income::count())->toBe(1);
});

test('an income category with incomes cannot be deleted', function () {
    $category = IncomeCategory::create(['name' => 'Выездная игра']);
    Income::create(['income_category_id' => $category->id, 'amount' => 1000, 'received_on' => '2026-10-02']);

    Livewire::actingAs(User::factory()->create())
        ->test(IncomesCrud::class)
        ->call('deleteCategory', $category->id)
        ->assertHasErrors('delete-category');

    expect(IncomeCategory::count())->toBe(1);
});

test('incomes are added to weekly revenue and profit but not to the average check', function () {
    $category = IncomeCategory::create(['name' => 'Выездная игра']);
    Income::create(['income_category_id' => $category->id, 'amount' => 25000, 'received_on' => '2026-10-18']); // последний день недели
    Income::create(['income_category_id' => $category->id, 'amount' => 999, 'received_on' => '2026-10-25']); // другая неделя

    $report = app(WeeklyReportService::class)->generateFor(
        Carbon::parse('2026-10-12'),
        Carbon::parse('2026-10-18'),
    );

    expect((float) $report->revenue)->toBe(25000.0);
    expect((float) $report->profit)->toBe(25000.0);
    expect($report->avg_check)->toBeNull();
});

test('analytics month revenue includes incomes', function () {
    $category = IncomeCategory::create(['name' => 'Выездная игра']);
    Income::create(['income_category_id' => $category->id, 'amount' => 15000, 'received_on' => now()->startOfMonth()->toDateString()]);

    $component = Livewire::actingAs(User::factory()->create())->test(AnalyticsDashboard::class);

    expect($component->instance()->currentMonthRevenue)->toBe(15000.0);
    expect($component->instance()->revenueByMonth[now()->format('Y-m')])->toBe(15000.0);
});
