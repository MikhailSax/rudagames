<?php

namespace App\Livewire\Admin;

use App\Models\Communication;
use App\Models\GameParticipation;
use App\Models\Team;
use Carbon\CarbonInterface;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AnalyticsDashboard extends Component
{
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
        $rows = GameParticipation::query()
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->where('games.played_at', '<=', now())
            ->selectRaw("DATE_FORMAT(games.played_at, '%Y-%m') as month, SUM(game_participations.revenue) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $rows->pluck('total', 'month')->toArray();
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
