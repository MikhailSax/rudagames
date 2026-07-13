<?php

namespace App\Console\Commands;

use App\Services\Import\TeamsGamesImportService;
use Illuminate\Console\Command;

class ImportTeamsGames extends Command
{
    protected $signature = 'import:teams-games {file : Путь к xlsx-файлу выгрузки}';

    protected $description = 'Импорт команд, игр и участий из xlsx-выгрузки rudagames.com';

    public function handle(TeamsGamesImportService $importer): int
    {
        $path = $this->argument('file');

        if (! file_exists($path)) {
            $this->error("Файл не найден: {$path}");
            return self::FAILURE;
        }

        $result = $importer->importFromFile($path);

        $this->info('Импорт завершён.');
        $this->table(array_keys($result['stats']), [array_values($result['stats'])]);

        if (! empty($result['skipped'])) {
            $this->warn('Пропущенные строки:');
            $this->table(['row', 'team', 'phone', 'reason'], $result['skipped']);
        }

        return self::SUCCESS;
    }
}
