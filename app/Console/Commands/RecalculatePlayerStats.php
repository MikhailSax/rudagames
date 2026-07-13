<?php

namespace App\Console\Commands;

use App\Models\Player;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculatePlayerStats extends Command
{
    protected $signature = 'players:recalculate-stats';

    protected $description = 'Модуль 4: пересчитывает games_count для всех игроков на основе участий их команд';

    public function handle(): int
    {
        $processed = 0;

        Player::query()->chunkById(200, function ($players) use (&$processed) {
            foreach ($players as $player) {
                $gamesCount = DB::table('game_participations')
                    ->join('team_player', 'team_player.team_id', '=', 'game_participations.team_id')
                    ->where('team_player.player_id', $player->id)
                    ->distinct('game_participations.game_id')
                    ->count('game_participations.game_id');

                $player->update(['games_count' => $gamesCount]);
                $processed++;
            }
        });

        $this->info("Пересчитано игроков: {$processed}");

        return self::SUCCESS;
    }
}
