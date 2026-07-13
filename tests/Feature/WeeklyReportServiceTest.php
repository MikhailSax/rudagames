<?php

use App\Models\WeeklyReport;
use App\Services\Reports\WeeklyReportService;
use Carbon\CarbonImmutable;

test('generateFor accepts CarbonImmutable, which is what now() returns in this app', function () {
    // Регрессия: AppServiceProvider настраивает Date::use(CarbonImmutable::class),
    // поэтому now() везде в приложении отдаёт CarbonImmutable, а не Illuminate\Support\Carbon.
    // Метод обязан принимать оба варианта через CarbonInterface.
    $service = new WeeklyReportService;

    $report = $service->generateFor(
        CarbonImmutable::now()->subWeek()->startOfWeek(),
        CarbonImmutable::now()->subWeek()->endOfWeek(),
    );

    expect($report)->toBeInstanceOf(WeeklyReport::class);
});

test('generating a report for the same week twice updates instead of crashing or duplicating', function () {
    // Регрессия: updateOrCreate искал по toDateString() ('2026-07-06'), а 'date'-каст хранит
    // значение как полный datetime ('2026-07-06 00:00:00') — поиск никогда не находил
    // существующую запись, и повторная генерация той же недели падала на unique-constraint.
    $service = new WeeklyReportService;
    $start = CarbonImmutable::now()->subWeek()->startOfWeek();
    $end = CarbonImmutable::now()->subWeek()->endOfWeek();

    $first = $service->generateFor($start, $end);
    $second = $service->generateFor($start, $end);

    expect(WeeklyReport::count())->toBe(1);
    expect($second->id)->toBe($first->id);
});

test('generateForPreviousWeek works end to end', function () {
    $service = new WeeklyReportService;

    $report = $service->generateForPreviousWeek();

    expect(WeeklyReport::count())->toBe(1);
    expect($report->period_start->isBefore($report->period_end))->toBeTrue();
});
