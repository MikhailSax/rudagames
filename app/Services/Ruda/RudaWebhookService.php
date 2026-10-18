<?php

namespace App\Services\Ruda;

use App\Models\Game;
use App\Models\GameCategory;
use App\Models\GameParticipation;
use App\Models\Product;
use App\Models\RudaRegistration;
use App\Models\Team;
use App\Models\TeamNameHistory;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Обработка вебхуков платформы Руда (см. «Ruda. Документация по API Вебхуков»):
 *
 *  - eventCreated        — серия мероприятий заказана/изменена → создаём/обновляем игры;
 *  - teamRegistered      — команда подала заявку → сохраняем заявку (контакты капитана);
 *  - statisticsSubmitted — ведущий внёс итоги → создаём участия команд с выручкой.
 *
 * Руда не передаёт телефон в статистике, поэтому команда сопоставляется так:
 * teamId из Руды (teams.ruda_team_id) → заявка на эту же игру с тем же названием
 * команды → телефон из заявки (как в Excel-импорте, телефон — ключ команды) →
 * однозначное совпадение названия с командой, уже известной CRM → новая команда
 * без телефона (новичок, заявка которого к нам не пришла).
 */
class RudaWebhookService
{
    private const FALLBACK_CATEGORY = 'Без категории';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handleEventCreated(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $recordId = (string) $data['id'];
            $dayIds = [];
            $result = ['games_created' => 0, 'games_updated' => 0, 'games_deleted' => 0, 'games_kept' => []];

            foreach ($data['eventDays'] ?? [] as $day) {
                $game = $this->upsertGame(
                    dayId: (string) $day['id'],
                    name: (string) $data['name'],
                    date: $day['date'] ?? null,
                    parsedDate: $day['parsedDate'] ?? null,
                    timezone: $day['timezone'] ?? null,
                    place: $day['place'] ?? null,
                    cost: $day['cost'] ?? null,
                    recordId: $recordId,
                );

                $dayIds[] = $game->ruda_event_day_id;
                $result[$game->wasRecentlyCreated ? 'games_created' : 'games_updated']++;
            }

            // Игровой день убрали из серии: удаляем игру, только если по ней ещё нет участий
            $removed = Game::where('ruda_event_record_id', $recordId)
                ->whereNotIn('ruda_event_day_id', $dayIds)
                ->withCount('participations')
                ->get();

            foreach ($removed as $game) {
                if ($game->participations_count > 0) {
                    $result['games_kept'][] = $game->id;

                    continue;
                }

                $game->delete();
                $result['games_deleted']++;
            }

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handleTeamRegistered(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $game = $this->upsertGame(
                dayId: (string) $data['eventId'],
                name: (string) $data['eventName'],
                date: $data['date'] ?? null,
                parsedDate: $data['parsedDate'] ?? null,
                timezone: $data['timezone'] ?? null,
                place: $data['place'] ?? null,
            );

            $phone = $this->normalizePhone($data['captainPhone'] ?? null)
                ?? $this->normalizePhone($data['registrantPhone'] ?? null);

            // Команда появляется в CRM сразу при заявке: находим по телефону или создаём.
            // У команды без участий нет статусов, поэтому рассылки её не трогают,
            // пока она не сыграет.
            $team = $phone ? $this->findTeamByPhone($phone) : null;
            $teamCreated = false;

            // Новичок мог прийти раньше из итогов без телефона — дописываем ему телефон
            if (! $team && $phone && ($teamName = $this->clean($data['teamName'] ?? null))) {
                $team = $this->findTeamByName($teamName, withoutPhoneOnly: true);
                $team?->update(['phone' => $phone]);
            }

            if (! $team && $phone) {
                $team = Team::create([
                    'phone' => $phone,
                    'current_name' => $this->clean($data['teamName'] ?? null) ?? 'Без названия',
                    'captain_name' => $this->clean($data['captainName'] ?? null) ?? $this->clean($data['registrantName'] ?? null),
                    'email' => $this->clean($data['captainEmail'] ?? null) ?? $this->clean($data['registrantEmail'] ?? null),
                ]);
                $teamCreated = true;
            }

            $registration = RudaRegistration::updateOrCreate(
                ['play_application_id' => (int) $data['playApplicationId']],
                [
                    'ruda_event_day_id' => $game->ruda_event_day_id,
                    'game_id' => $game->id,
                    'team_id' => $team?->id,
                    'team_name' => $this->clean($data['teamName'] ?? null),
                    'registrant_name' => $this->clean($data['registrantName'] ?? null),
                    'registrant_phone' => $this->clean($data['registrantPhone'] ?? null),
                    'registrant_email' => $this->clean($data['registrantEmail'] ?? null),
                    'captain_name' => $this->clean($data['captainName'] ?? null),
                    'captain_phone' => $this->clean($data['captainPhone'] ?? null),
                    'captain_email' => $this->clean($data['captainEmail'] ?? null),
                ],
            );

            return [
                'registration_id' => $registration->id,
                'game_id' => $game->id,
                'team_id' => $team?->id,
                'team_created' => $teamCreated,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handleStatisticsSubmitted(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $game = $this->upsertGame(
                dayId: (string) $data['eventId'],
                name: (string) $data['eventName'],
                date: $data['date'] ?? null,
                parsedDate: $data['parsedDate'] ?? null,
                timezone: $data['timezone'] ?? null,
                place: $data['place'] ?? null,
                cost: $data['cost'] ?? null,
            );

            $result = ['game_id' => $game->id, 'participations' => 0, 'teams_created' => 0, 'unmatched' => []];

            foreach ($data['teams'] ?? [] as $row) {
                $teamName = $this->clean($row['name'] ?? null) ?? 'Без названия';
                $rudaTeamId = isset($row['teamId']) ? (int) $row['teamId'] : null;

                $team = $this->resolveTeam($game, $teamName, $rudaTeamId, $result);

                if (! $team) {
                    $result['unmatched'][] = $teamName;

                    continue;
                }

                $this->trackTeamName($team, $teamName, $game->played_at);

                GameParticipation::updateOrCreate(
                    ['game_id' => $game->id, 'team_id' => $team->id],
                    [
                        'team_name_at_time' => $teamName,
                        'players_count' => (int) ($row['actualPlayerCount'] ?? 0),
                        'revenue' => (float) ($row['revenue'] ?? 0),
                    ],
                );
                $result['participations']++;
            }

            return $result;
        });
    }

    /**
     * Находит игру по ID мероприятия Руды. Если её нет — ищет такую же игру,
     * загруженную ранее из Excel (название + время), чтобы не плодить дубли.
     */
    private function upsertGame(
        string $dayId,
        string $name,
        ?string $date,
        ?string $parsedDate,
        ?string $timezone,
        ?string $place,
        float|int|string|null $cost = null,
        ?string $recordId = null,
    ): Game {
        $playedAt = $this->parseDate($date, $parsedDate, $timezone);
        $name = trim($name);

        $game = Game::where('ruda_event_day_id', $dayId)->first()
            ?? Game::whereNull('ruda_event_day_id')
                ->where('name', $name)
                ->where('played_at', $playedAt)
                ->first();

        if (! $game) {
            [$productId, $categoryId] = $this->resolveProductAndCategory($name);

            $game = new Game([
                'product_id' => $productId,
                'category_id' => $categoryId,
            ]);
        }

        $game->fill([
            'ruda_event_day_id' => $dayId,
            'name' => $name,
            'played_at' => $playedAt,
        ]);

        if ($recordId !== null) {
            $game->ruda_event_record_id = $recordId;
        }
        if ($place !== null) {
            $game->venue = $this->clean($place);
        }
        if ($cost !== null) {
            $game->cost = (float) $cost;
        }

        $game->save();

        return $game;
    }

    /**
     * Руда не сообщает продукт/категорию. Берём их у последней игры с тем же
     * названием, иначе — продукт по умолчанию из настроек (поправить можно в админке).
     *
     * @return array{0: int, 1: int}
     */
    private function resolveProductAndCategory(string $name): array
    {
        $sameName = Game::where('name', $name)->latest('played_at')->first();

        if ($sameName) {
            return [$sameName->product_id, $sameName->category_id];
        }

        $product = Product::firstOrCreate(['name' => config('services.ruda.default_product')]);
        $category = GameCategory::firstOrCreate([
            'product_id' => $product->id,
            'name' => self::FALLBACK_CATEGORY,
        ]);

        return [$product->id, $category->id];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function resolveTeam(Game $game, string $teamName, ?int $rudaTeamId, array &$result): ?Team
    {
        if ($rudaTeamId && $team = Team::where('ruda_team_id', $rudaTeamId)->first()) {
            return $team;
        }

        $registration = RudaRegistration::where('ruda_event_day_id', $game->ruda_event_day_id)
            ->get()
            ->first(fn (RudaRegistration $r) => $r->team_name !== null
                && mb_strtolower($r->team_name) === mb_strtolower($teamName));

        if (! $registration) {
            $team = $this->findTeamByName($teamName);

            if ($team && $rudaTeamId && ! $team->ruda_team_id && ! Team::where('ruda_team_id', $rudaTeamId)->exists()) {
                $team->update(['ruda_team_id' => $rudaTeamId]);
            }

            // Команда-новичок без заявки: создаём без телефона, по ID Руды.
            // Без ID Руды не создаём — иначе каждая такая игра плодила бы дубли.
            if (! $team && $rudaTeamId) {
                $team = Team::create(['current_name' => $teamName, 'ruda_team_id' => $rudaTeamId]);
                $result['teams_created']++;
            }

            return $team;
        }

        $team = $registration->team;

        if (! $team) {
            $phone = $this->normalizePhone($registration->captain_phone)
                ?? $this->normalizePhone($registration->registrant_phone);

            if (! $phone) {
                return null;
            }

            $team = $this->findTeamByPhone($phone) ?? new Team(['phone' => $phone]);

            if (! $team->exists) {
                $team->current_name = $teamName;
                $team->captain_name = $registration->captain_name ?? $registration->registrant_name;
                $team->email = $registration->captain_email ?? $registration->registrant_email;
                $team->save();
                $result['teams_created']++;
            }

            $registration->update(['team_id' => $team->id]);
        }

        // Запоминаем ID Руды — дальше команда найдётся без заявки
        if ($rudaTeamId && ! $team->ruda_team_id && ! Team::where('ruda_team_id', $rudaTeamId)->exists()) {
            $team->update(['ruda_team_id' => $rudaTeamId]);
        }

        return $team;
    }

    /**
     * Запасной вариант, когда заявки нет (команда записалась до подключения
     * вебхуков или напрямую у ведущего): ищем уже известную CRM команду по текущему
     * названию или по истории названий. Берём только однозначное совпадение.
     */
    private function findTeamByName(string $teamName, bool $withoutPhoneOnly = false): ?Team
    {
        $needle = mb_strtolower($teamName);
        $teams = fn () => Team::query()->when($withoutPhoneOnly, fn ($q) => $q->whereNull('phone'));

        $ids = $teams()->where('current_name', $teamName)->pluck('id')
            ->merge($withoutPhoneOnly ? [] : TeamNameHistory::where('name', $teamName)->pluck('team_id'))
            ->unique()
            ->values();

        // В SQLite сравнение регистрозависимое — добираем совпадения без учёта регистра
        if ($ids->isEmpty()) {
            $ids = $teams()
                ->get(['id', 'current_name'])
                ->filter(fn (Team $t) => mb_strtolower($t->current_name) === $needle)
                ->pluck('id');
        }

        return $ids->count() === 1 ? Team::whereKey($ids->first())->first() : null;
    }

    /** История названий — та же логика, что в Excel-импорте. */
    private function trackTeamName(Team $team, string $teamName, CarbonInterface $playedAt): void
    {
        TeamNameHistory::firstOrCreate([
            'team_id' => $team->id,
            'name' => $teamName,
            'used_at' => $playedAt,
        ]);

        if ($team->current_name !== $teamName && $playedAt >= ($team->last_game_at ?? $playedAt)) {
            $team->update(['current_name' => $teamName]);
        }
    }

    /**
     * В CRM время игр хранится как местное время площадки (как в Excel-выгрузке).
     * На практике Руда присылает в `date` время не площадки (то московское, то UTC),
     * а местное время — в parsedDate, поэтому основной источник — parsedDate,
     * переведённый в часовой пояс мероприятия. `date` — только запасной вариант.
     */
    private function parseDate(?string $date, ?string $parsedDate, ?string $timezone): Carbon
    {
        if ($parsedDate) {
            $timezone = $timezone && in_array($timezone, \DateTimeZone::listIdentifiers(), true) ? $timezone : null;
            $local = $timezone
                ? Carbon::parse($parsedDate, $timezone)->setTimezone($timezone)
                : Carbon::parse($parsedDate);

            return Carbon::createFromFormat('Y-m-d H:i', $local->format('Y-m-d H:i'))->startOfMinute();
        }

        if ($date && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $date)) {
            return Carbon::createFromFormat('Y-m-d H:i', $date)->startOfMinute();
        }

        throw new \InvalidArgumentException('Не передана дата мероприятия');
    }

    /**
     * Excel-импорт хранит телефон как есть (только цифры), поэтому старые команды
     * могут лежать и с 7, и с 8 в начале — ищем оба варианта.
     */
    private function findTeamByPhone(string $phone): ?Team
    {
        return Team::where('phone', $phone)->first()
            ?? Team::where('phone', '8'.substr($phone, 1))->first();
    }

    /**
     * Приводит телефон к формату teams.phone (11 цифр, начинается с 7) или null.
     * Заглушки вроде +7(000)000-00-00 отбрасываем: код после 7 не может начинаться с 0–2.
     */
    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (strlen($digits) === 10) {
            $digits = '7'.$digits;
        } elseif (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7'.substr($digits, 1);
        }

        return preg_match('/^7[3-9]\d{9}$/', $digits) ? $digits : null;
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
