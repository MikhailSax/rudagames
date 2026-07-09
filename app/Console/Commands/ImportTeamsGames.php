<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameCategory;
use App\Models\GameParticipation;
use App\Models\Product;
use App\Models\Team;
use App\Models\TeamNameHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ImportTeamsGames extends Command
{
    protected $signature = 'import:teams-games {file : Путь к xlsx-файлу выгрузки}';

    protected $description = 'Импорт команд, игр и участий из xlsx-выгрузки rudagames.com';

    private const FALLBACK_CATEGORY = 'Без категории';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! file_exists($path)) {
            $this->error("Файл не найден: {$path}");
            return self::FAILURE;
        }

        $rows = Excel::toArray([], $path)[0];
        $header = array_map('trim', array_shift($rows));

        $stats = [
            'processed' => 0,
            'skipped' => 0,
            'teams_created' => 0,
            'teams_renamed' => 0,
            'games_created' => 0,
        ];
        $skippedRows = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2; // +2: заголовок + 1-индексация Excel
            $data = array_combine($header, $row);

            $phone = $this->normalizePhone($data['Телефон'] ?? '');

            if (strlen($phone) !== 11) {
                $skippedRows[] = [
                    'row' => $rowNum,
                    'team' => $data['Команда'] ?? '',
                    'phone' => $data['Телефон'] ?? '',
                    'reason' => 'Некорректный телефон после нормализации: '.$phone,
                ];
                $stats['skipped']++;
                continue;
            }

            try {
                DB::transaction(function () use ($data, $phone, &$stats) {
                    $product = Product::firstOrCreate(['name' => trim($data['Продукт'])]);

                    $categoryName = trim($data['Категория'] ?? '') ?: self::FALLBACK_CATEGORY;
                    $category = GameCategory::firstOrCreate([
                        'product_id' => $product->id,
                        'name' => $categoryName,
                    ]);

                    $playedAt = $this->parseDate($data['Дата игры']);

                    $game = Game::firstOrCreate(
                        [
                            'product_id' => $product->id,
                            'category_id' => $category->id,
                            'name' => trim($data['Пакет']),
                            'played_at' => $playedAt,
                        ]
                    );
                    if ($game->wasRecentlyCreated) {
                        $stats['games_created']++;
                    }

                    $teamName = trim($data['Команда']);
                    $team = Team::firstOrNew(['phone' => $phone]);
                    $isNewTeam = ! $team->exists;

                    if ($isNewTeam) {
                        $team->current_name = $teamName;
                        $team->captain_name = trim($data['Имя капитана'] ?? '') ?: null;
                        $team->email = trim($data['Почта'] ?? '') ?: null;
                        $team->save();
                        $stats['teams_created']++;

                        TeamNameHistory::create([
                            'team_id' => $team->id,
                            'name' => $teamName,
                            'used_at' => $playedAt,
                        ]);
                    } elseif ($team->current_name !== $teamName) {
                        // команда играла раньше под другим именем — фиксируем историю
                        TeamNameHistory::firstOrCreate([
                            'team_id' => $team->id,
                            'name' => $teamName,
                            'used_at' => $playedAt,
                        ]);

                        if ($playedAt >= ($team->last_game_at ?? $playedAt)) {
                            $team->current_name = $teamName;
                            $team->save();
                        }
                        $stats['teams_renamed']++;
                    }

                    GameParticipation::firstOrCreate(
                        [
                            'game_id' => $game->id,
                            'team_id' => $team->id,
                        ],
                        [
                            'team_name_at_time' => $teamName,
                            'players_count' => (int) $data['Игроков сыграло'],
                            'revenue' => (float) $data['Выручка за команду'],
                        ]
                    );
                });

                $stats['processed']++;
            } catch (\Throwable $e) {
                $skippedRows[] = [
                    'row' => $rowNum,
                    'team' => $data['Команда'] ?? '',
                    'phone' => $data['Телефон'] ?? '',
                    'reason' => 'Ошибка: '.$e->getMessage(),
                ];
                $stats['skipped']++;
            }
        }

        $this->info('Импорт завершён.');
        $this->table(array_keys($stats), [array_values($stats)]);

        if (! empty($skippedRows)) {
            $this->warn('Пропущенные строки:');
            $this->table(['row', 'team', 'phone', 'reason'], $skippedRows);
        }

        return self::SUCCESS;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    private function parseDate($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        // Excel иногда отдаёт дату как serial number
        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
                ->format('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s', strtotime((string) $value));
    }
}
