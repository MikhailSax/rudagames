<?php

namespace App\Console\Commands;

use App\Models\Communication;
use App\Models\Team;
use Illuminate\Console\Command;

class SeedMilestoneCongratulations extends Command
{
    protected $signature = 'marketing:seed-congratulations
                            {--dry-run : Показать, что будет сделано, без записи в БД}';

    protected $description = 'Разовая команда: помечает уже достигшие уровня "постоянная"/"ядро сообщества" '
    . 'команды как уже поздравленные — чтобы Marketing Engine не разослал всем поздравления разом '
    . 'при первом запуске на исторических данных';

    public function handle(): int
    {
        $dryRun = (bool)$this->option('dry-run');

        $teams = Team::whereIn('lifecycle_stage', ['постоянная', 'ядро сообщества'])->get();

        $toSeed = $teams->filter(function (Team $team) {
            return !Communication::where('team_id', $team->id)
                ->where('goal', 'поздравить')
                ->where('message_text', 'like', '%' . $team->lifecycle_stage . '%')
                ->exists();
        });

        $this->info("Найдено команд для пометки: {$toSeed->count()} из {$teams->count()}");

        if ($dryRun) {
            $this->table(
                ['team_id', 'current_name', 'lifecycle_stage'],
                $toSeed->map(fn(Team $t) => [$t->id, $t->current_name, $t->lifecycle_stage])->toArray()
            );
            $this->comment('Dry-run: записи не созданы. Запустите без --dry-run, чтобы применить.');

            return self::SUCCESS;
        }

        foreach ($toSeed as $team) {
            Communication::create([
                'team_id' => $team->id,
                'channel' => 'sms',
                'goal' => 'поздравить',
                'status' => 'историческая пометка',
                'message_text' => "[seed] команда уже была {$team->lifecycle_stage} до запуска Marketing Engine",
                'sent_at' => now(),
            ]);
        }

        $this->info("Помечено команд: {$toSeed->count()}");

        return self::SUCCESS;
    }
}
