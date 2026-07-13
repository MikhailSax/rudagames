<?php

namespace App\Console\Commands;

use App\Services\Reports\WeeklyReportService;
use Illuminate\Console\Command;

class GenerateWeeklyReport extends Command
{
    protected $signature = 'reports:generate-weekly {--week= : ISO-дата любого дня нужной недели (по умолчанию — прошлая неделя)}';

    protected $description = 'Модуль 8: формирует еженедельный отчёт (продажи, клиенты, маркетинг) без AI-выводов';

    public function handle(WeeklyReportService $service): int
    {
        $weekOption = $this->option('week');

        $report = $weekOption
            ? $service->generateFor(
                \Illuminate\Support\Carbon::parse($weekOption)->startOfWeek(),
                \Illuminate\Support\Carbon::parse($weekOption)->endOfWeek(),
            )
            : $service->generateForPreviousWeek();

        $this->info("Отчёт сформирован: {$report->period_start->format('d.m.Y')} — {$report->period_end->format('d.m.Y')}");
        $this->table(
            ['revenue', 'profit', 'avg_check', 'avg_team_size', 'new', 'returning', 'lost', 'reactivated', 'sms_sent', 'sms_conversion'],
            [[
                $report->revenue,
                $report->profit,
                $report->avg_check,
                $report->avg_team_size,
                $report->new_teams_count,
                $report->returning_teams_count,
                $report->lost_teams_count,
                $report->reactivated_teams_count,
                $report->sms_sent_count,
                $report->sms_conversion_count,
            ]]
        );

        return self::SUCCESS;
    }
}
