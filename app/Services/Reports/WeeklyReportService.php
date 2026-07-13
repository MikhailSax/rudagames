<?php

namespace App\Services\Reports;

use App\Models\Communication;
use App\Models\Game;
use App\Models\GameParticipation;
use App\Models\Team;
use App\Models\WeeklyReport;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Модуль 8: еженедельный отчёт (без AI-выводов — они формируются отдельно через Claude API
 * и дописываются в поле ai_summary уже после создания отчёта этим сервисом).
 *
 * Часть метрик (потерянные/реактивированные команды, конверсия SMS) не хранится как явные
 * статусные переходы в БД, поэтому считается эвристически — см. комментарии у каждого блока.
 */
class WeeklyReportService
{
    public function generateFor(CarbonInterface $periodStart, CarbonInterface $periodEnd): WeeklyReport
    {
        $periodStart = $periodStart->copy()->startOfDay();
        $periodEnd = $periodEnd->copy()->endOfDay();

        $sales = $this->calculateSales($periodStart, $periodEnd);
        $clients = $this->calculateClients($periodStart, $periodEnd);
        $marketing = $this->calculateMarketing($periodStart, $periodEnd);

        // Не используем updateOrCreate(['period_start' => ..., 'period_end' => ...], ...) напрямую:
        // 'date'-каст хранит значение как полный datetime ('2026-07-06 00:00:00'), а сравнение
        // с обычной строкой toDateString() ('2026-07-06') никогда не совпадёт — updateOrCreate
        // каждый раз пытался бы INSERT и падал на unique-constraint при повторной генерации той
        // же недели. whereDate() сравнивает по дате независимо от формата хранения.
        $report = WeeklyReport::query()
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->first();

        $attributes = [...$sales, ...$clients, ...$marketing];

        if ($report) {
            $report->update($attributes);

            return $report;
        }

        return WeeklyReport::create([
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            ...$attributes,
        ]);
    }

    /**
     * Предыдущая календарная неделя (пн-вс) — удобный дефолт для планового запуска по расписанию.
     */
    public function generateForPreviousWeek(): WeeklyReport
    {
        $start = now()->subWeek()->startOfWeek();
        $end = now()->subWeek()->endOfWeek();

        return $this->generateFor($start, $end);
    }

    private function calculateSales(CarbonInterface $start, CarbonInterface $end): array
    {
        $participations = GameParticipation::query()
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->whereBetween('games.played_at', [$start, $end]);

        $revenue = (clone $participations)->sum('game_participations.revenue');
        $teamsCount = (clone $participations)->count();
        $avgTeamSize = (clone $participations)->avg('game_participations.players_count');

        // Прибыль считаем по играм с уже внесёнными фактическими финансами (Модуль 2)
        $profit = Game::query()
            ->whereBetween('played_at', [$start, $end])
            ->whereNotNull('actual_revenue')
            ->whereNotNull('actual_expenses')
            ->selectRaw('COALESCE(SUM(actual_revenue - actual_expenses), 0) as profit')
            ->value('profit');

        return [
            'revenue' => (float) $revenue,
            'profit' => (float) $profit,
            'avg_check' => $teamsCount > 0 ? round($revenue / $teamsCount, 2) : null,
            'avg_team_size' => $avgTeamSize !== null ? round((float) $avgTeamSize, 2) : null,
        ];
    }

    private function calculateClients(CarbonInterface $start, CarbonInterface $end): array
    {
        // Новые: первая игра команды пришлась на этот период
        $newTeamsCount = Team::query()
            ->whereBetween('first_game_at', [$start, $end])
            ->count();

        // Повторные: играли в этот период, но не впервые
        $returningTeamsCount = GameParticipation::query()
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->join('teams', 'teams.id', '=', 'game_participations.team_id')
            ->whereBetween('games.played_at', [$start, $end])
            ->where(function ($q) use ($start) {
                $q->whereNull('teams.first_game_at')
                    ->orWhere('teams.first_game_at', '<', $start);
            })
            ->distinct('game_participations.team_id')
            ->count('game_participations.team_id');

        // Потерянные: команда сейчас "потерянная", и её последняя игра была ровно настолько давно
        // (365+ дней — порог из teams:recalculate-profiles), что статус "потерянная" наступил
        // именно в этом периоде. Эвристика, т.к. явной истории смены статусов не ведётся.
        $lostTeamsCount = Team::query()
            ->where('activity_status', 'потерянная')
            ->whereBetween('last_game_at', [
                $start->copy()->subDays(365),
                $end->copy()->subDays(365),
            ])
            ->count();

        // Реактивированные: сыграли в этот период после перерыва 90+ дней с предыдущей игры
        // (90 дней — порог "остывающая"→"спящая" из teams:recalculate-profiles).
        $reactivatedTeamsCount = $this->countReactivatedTeams($start, $end);

        return [
            'new_teams_count' => $newTeamsCount,
            'returning_teams_count' => $returningTeamsCount,
            'lost_teams_count' => $lostTeamsCount,
            'reactivated_teams_count' => $reactivatedTeamsCount,
        ];
    }

    private function countReactivatedTeams(CarbonInterface $start, CarbonInterface $end): int
    {
        $teamIds = GameParticipation::query()
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->whereBetween('games.played_at', [$start, $end])
            ->pluck('game_participations.team_id')
            ->unique();

        if ($teamIds->isEmpty()) {
            return 0;
        }

        $count = 0;

        foreach ($teamIds as $teamId) {
            $gamesInPeriod = DB::table('game_participations')
                ->join('games', 'games.id', '=', 'game_participations.game_id')
                ->where('game_participations.team_id', $teamId)
                ->whereBetween('games.played_at', [$start, $end])
                ->pluck('games.played_at');

            $earliestThisPeriod = $gamesInPeriod->min();

            $previousGame = DB::table('game_participations')
                ->join('games', 'games.id', '=', 'game_participations.game_id')
                ->where('game_participations.team_id', $teamId)
                ->where('games.played_at', '<', $earliestThisPeriod)
                ->max('games.played_at');

            if ($previousGame && Carbon::parse($previousGame)->diffInDays(Carbon::parse($earliestThisPeriod)) >= 90) {
                $count++;
            }
        }

        return $count;
    }

    private function calculateMarketing(CarbonInterface $start, CarbonInterface $end): array
    {
        $smsSentCount = Communication::query()
            ->where('channel', 'sms')
            ->where('status', Communication::STATUS_SENT)
            ->whereBetween('sent_at', [$start, $end])
            ->count();

        // Конверсия: команде отправили SMS в этот период, и она зарегистрировалась
        // на игру в течение 14 дней после отправки.
        $smsConversionCount = Communication::query()
            ->where('channel', 'sms')
            ->where('status', Communication::STATUS_SENT)
            ->whereBetween('sent_at', [$start, $end])
            ->get()
            ->filter(function (Communication $comm) {
                return GameParticipation::query()
                    ->join('games', 'games.id', '=', 'game_participations.game_id')
                    ->where('game_participations.team_id', $comm->team_id)
                    ->whereBetween('games.played_at', [$comm->sent_at, $comm->sent_at->copy()->addDays(14)])
                    ->exists();
            })
            ->count();

        return [
            'sms_sent_count' => $smsSentCount,
            'sms_conversion_count' => $smsConversionCount,
        ];
    }
}
