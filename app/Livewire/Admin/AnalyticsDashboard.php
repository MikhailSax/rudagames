<?php

namespace App\Livewire\Admin;

use App\Models\Communication;
use App\Models\GameParticipation;
use App\Models\MonthlyGoal;
use App\Models\Team;
use Carbon\CarbonInterface;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AnalyticsDashboard extends Component
{
    public string $goalInput = '';

    private function periodStart(): ?CarbonInterface
    {
        return now()->subDays(30);
    }

    #[Computed]
    public function kpis(): array
    {
        $start = $this->periodStart();

        $participations = GameParticipation::query()
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->when($start, fn ($q) => $q->where('games.played_at', '>=', $start))
            ->where('games.played_at', '<=', now());

        $revenue = (clone $participations)->sum('game_participations.revenue');
        $gamesPlayed = (clone $participations)->distinct('game_participations.game_id')->count('game_participations.game_id');
        $avgTeamSize = (clone $participations)->avg('game_participations.players_count');

        $newTeams = Team::when($start, fn ($q) => $q->where('first_game_at', '>=', $start))->count();

        return [
            'revenue' => (float) $revenue,
            'games_played' => (int) $gamesPlayed,
            'avg_team_size' => round((float) $avgTeamSize, 1),
            'new_teams' => $newTeams,
            'active_teams' => Team::where('activity_status', 'активная')->count(),
            'dormant_teams' => Team::where('activity_status', 'спящая')->count(),
            'lost_teams' => Team::where('activity_status', 'потерянная')->count(),
            'pending_drafts' => Communication::drafts()->count(),
        ];
    }

    /**
     * Модуль 9: показатели «сегодня» и «на этой неделе» — то, что руководитель видит
     * при открытии CRM с утра.
     */
    #[Computed]
    public function todayKpis(): array
    {
        $today = now();
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        $newRegistrationsToday = GameParticipation::whereDate('created_at', $today)->count();

        $teamsThisWeek = GameParticipation::query()
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->whereBetween('games.played_at', [$weekStart, $weekEnd])
            ->distinct('game_participations.team_id')
            ->count('game_participations.team_id');

        // Ожидаемая выручка: стоимость участия × число уже зарегистрированных команд
        // по предстоящим играм на этой неделе.
        $expectedRevenue = GameParticipation::query()
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->whereBetween('games.played_at', [now(), $weekEnd])
            ->selectRaw('COALESCE(SUM(games.cost), 0) as total')
            ->value('total');

        $smsToday = Communication::query()
            ->where('channel', 'sms')
            ->where('status', Communication::STATUS_SENT)
            ->whereDate('sent_at', $today)
            ->count();

        return [
            'new_registrations_today' => $newRegistrationsToday,
            'teams_this_week' => $teamsThisWeek,
            'expected_revenue' => (float) $expectedRevenue,
            'need_to_return' => Communication::drafts()->where('goal', 'вернуть')->count(),
            'sms_today' => $smsToday,
        ];
    }

    /**
     * Модуль 9: «Главные события» — опирается на черновики Marketing Engine (Модуль 6):
     * каждая цель черновика уже отражает конкретное событие в жизни команды.
     */
    #[Computed]
    public function mainEvents(): array
    {
        return [
            'new_teams' => Team::where('first_game_at', '>=', now()->subDays(7))->count(),
            'milestones_reached' => Communication::drafts()->where('goal', 'поздравить')->count(),
            'need_contact' => Communication::drafts()->count(),
            'need_second_game' => Communication::drafts()->where('goal', 'довести до второй игры')->count(),
        ];
    }

    #[Computed]
    public function currentMonthGoal(): ?MonthlyGoal
    {
        return MonthlyGoal::whereDate('month', now()->startOfMonth()->toDateString())->first();
    }

    #[Computed]
    public function currentMonthRevenue(): float
    {
        return (float) GameParticipation::query()
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->whereBetween('games.played_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('game_participations.revenue');
    }

    #[Computed]
    public function monthGoalProgressPercent(): ?float
    {
        $goal = $this->currentMonthGoal;

        if (! $goal || (float) $goal->target_revenue <= 0) {
            return null;
        }

        return round(min(100, $this->currentMonthRevenue / (float) $goal->target_revenue * 100), 1);
    }

    public function saveGoal(): void
    {
        $this->validate([
            'goalInput' => 'required|numeric|min:1',
        ]);

        MonthlyGoal::updateOrCreate(
            ['month' => now()->startOfMonth()->toDateString()],
            ['target_revenue' => $this->goalInput]
        );

        $this->goalInput = '';
        unset($this->currentMonthGoal, $this->monthGoalProgressPercent);
    }

    #[Computed]
    public function lifecycleBreakdown(): array
    {
        return Team::query()
            ->selectRaw('lifecycle_stage, count(*) as cnt')
            ->whereNotNull('lifecycle_stage')
            ->groupBy('lifecycle_stage')
            ->pluck('cnt', 'lifecycle_stage')
            ->toArray();
    }

    #[Computed]
    public function activityBreakdown(): array
    {
        return Team::query()
            ->selectRaw('activity_status, count(*) as cnt')
            ->whereNotNull('activity_status')
            ->groupBy('activity_status')
            ->pluck('cnt', 'activity_status')
            ->toArray();
    }

    #[Computed]
    public function revenueByMonth(): array
    {
        // Группировка в PHP вместо SQL DATE_FORMAT — тот был завязан на MySQL
        // и падал на sqlite (дефолт в .env.example этого проекта).
        return GameParticipation::query()
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->where('games.played_at', '<=', now())
            ->get(['games.played_at', 'game_participations.revenue'])
            ->groupBy(fn ($row) => \Carbon\Carbon::parse($row->played_at)->format('Y-m'))
            ->map(fn ($rows) => (float) $rows->sum('revenue'))
            ->sortKeys()
            ->toArray();
    }

    #[Computed]
    public function topProducts(): array
    {
        return GameParticipation::query()
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->join('products', 'products.id', '=', 'games.product_id')
            ->selectRaw('products.name, count(*) as cnt')
            ->groupBy('products.name')
            ->orderByDesc('cnt')
            ->limit(6)
            ->pluck('cnt', 'name')
            ->toArray();
    }

    public function render()
    {
        return view('livewire.admin.analytics-dashboard')
            ->layoutData(['title' => 'Аналитика']);
    }
}
