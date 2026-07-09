<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecalculateTeamProfiles extends Command
{
    protected $signature = 'teams:recalculate-profiles';

    protected $description = 'Пересчитывает агрегаты (Модуль 3) и Marketing Profile (Модуль 5) для всех команд';

    // Модуль 3 / 5: пороги lifecycle_stage — по количеству сыгранных игр
    private const LIFECYCLE_NEW_MAX = 1;
    private const LIFECYCLE_DEVELOPING_MAX = 4;
    private const LIFECYCLE_REGULAR_MAX = 14;
    // 15+ игр -> "ядро сообщества"

    // Модуль 5: пороги activity_status — дней с последней игры
    private const ACTIVITY_ACTIVE_DAYS = 30;
    private const ACTIVITY_COOLING_DAYS = 90;
    private const ACTIVITY_DORMANT_DAYS = 365;

    // 365+ дней -> "потерянная"

    public function handle(): int
    {
        $now = Carbon::now();
        $processed = 0;

        Team::query()->chunkById(200, function ($teams) use ($now, &$processed) {
            foreach ($teams as $team) {
                $this->recalculateOne($team, $now);
                $processed++;
            }
        });

        $this->info("Пересчитано команд: {$processed}");

        return self::SUCCESS;
    }

    private function recalculateOne(Team $team, Carbon $now): void
    {
        $agg = DB::table('game_participations')
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->where('game_participations.team_id', $team->id)
            ->selectRaw('
                COUNT(*) as games_count,
                MIN(games.played_at) as first_game_at,
                MAX(games.played_at) as last_game_at,
                SUM(game_participations.revenue) as total_revenue,
                AVG(game_participations.players_count) as avg_team_size
            ')
            ->first();

        if (!$agg || $agg->games_count === 0) {
            return; // у команды нет участий — оставляем поля как есть
        }

        $firstGame = Carbon::parse($agg->first_game_at);
        $lastGame = Carbon::parse($agg->last_game_at);
        $gamesCount = (int)$agg->games_count;

        // Средний интервал между играми: суммарный охват / (кол-во игр - 1)
        // Если игра всего одна — интервал не определён.
        $avgIntervalDays = $gamesCount > 1
            ? (int)round($firstGame->diffInDays($lastGame) / ($gamesCount - 1))
            : null;

        // Есть ли у команды будущая (ещё не прошедшая) игра —
        // тогда команда не может считаться "остывающей/потерянной"
        $hasUpcomingGame = DB::table('game_participations')
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->where('game_participations.team_id', $team->id)
            ->where('games.played_at', '>', $now)
            ->exists();

        $favoriteProductId = DB::table('game_participations')
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->where('game_participations.team_id', $team->id)
            ->select('games.product_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('games.product_id')
            ->orderByDesc('cnt')
            ->value('games.product_id');

        $team->update([
            'first_game_at' => $firstGame,
            'last_game_at' => $lastGame,
            'games_count' => $gamesCount,
            'total_revenue' => $agg->total_revenue,
            'avg_team_size' => round($agg->avg_team_size, 2),
            'avg_interval_days' => $avgIntervalDays,
            'lifecycle_stage' => $this->lifecycleStage($gamesCount),
            'activity_status' => $hasUpcomingGame
                ? 'активная'
                : $this->activityStatus($lastGame, $now),
            'ltv' => $agg->total_revenue,
            'favorite_product_id' => $favoriteProductId,
        ]);
    }

    private function lifecycleStage(int $gamesCount): string
    {
        return match (true) {
            $gamesCount <= self::LIFECYCLE_NEW_MAX => 'новая',
            $gamesCount <= self::LIFECYCLE_DEVELOPING_MAX => 'развивающаяся',
            $gamesCount <= self::LIFECYCLE_REGULAR_MAX => 'постоянная',
            default => 'ядро сообщества',
        };
    }

    private function activityStatus(Carbon $lastGame, Carbon $now): string
    {
        $daysSince = $lastGame->diffInDays($now);

        return match (true) {
            $daysSince <= self::ACTIVITY_ACTIVE_DAYS => 'активная',
            $daysSince <= self::ACTIVITY_COOLING_DAYS => 'остывающая',
            $daysSince <= self::ACTIVITY_DORMANT_DAYS => 'спящая',
            default => 'потерянная',
        };
    }
}
