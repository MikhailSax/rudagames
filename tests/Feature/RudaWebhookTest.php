<?php

use App\Livewire\Admin\GameShow;
use App\Livewire\Admin\TeamsCrud;
use App\Models\Game;
use App\Models\GameCategory;
use App\Models\GameParticipation;
use App\Models\Product;
use App\Models\RudaRegistration;
use App\Models\RudaWebhookCall;
use App\Models\Team;
use App\Models\TeamNameHistory;
use App\Models\User;

const RUDA_SECRET = 'test-secret';
const RUDA_DAY_1 = '11111111-1111-1111-1111-111111111111';
const RUDA_DAY_2 = '22222222-2222-2222-2222-222222222222';
const RUDA_RECORD = '99999999-9999-9999-9999-999999999999';

beforeEach(function () {
    config(['services.ruda.webhook_secret' => RUDA_SECRET, 'services.ruda.default_product' => 'Мозгобойня']);
});

function rudaPost(string $event, array $payload, string $secret = RUDA_SECRET)
{
    return test()->postJson("/ruda/webhook/{$secret}/{$event}", $payload);
}

function rudaDay(string $id, string $date, ?float $cost = 500): array
{
    return [
        'id' => $id,
        'date' => $date,
        'parsedDate' => str_replace(' ', 'T', $date).':00+08:00',
        'timezone' => 'Asia/Irkutsk',
        'place' => 'Бар «Рудник»',
        'cost' => $cost,
    ];
}

function rudaRegistration(int $id, string $teamName, ?string $phone, string $dayId = RUDA_DAY_1): array
{
    return [
        'playApplicationId' => $id,
        'eventId' => $dayId,
        'eventName' => 'Классическая #120',
        'date' => '2026-10-10 19:00',
        'parsedDate' => '2026-10-10T19:00:00+08:00',
        'timezone' => 'Asia/Irkutsk',
        'place' => 'Бар «Рудник»',
        'teamName' => $teamName,
        'registrantName' => 'Иван',
        'registrantPhone' => $phone,
        'registrantEmail' => 'ivan@example.com',
        'captainName' => null,
        'captainPhone' => null,
        'captainEmail' => null,
    ];
}

function rudaStatistics(array $teams, string $dayId = RUDA_DAY_1): array
{
    return [
        'eventId' => $dayId,
        'eventName' => 'Классическая #120',
        'date' => '2026-10-10 19:00',
        'parsedDate' => '2026-10-10T19:00:00+08:00',
        'timezone' => 'Asia/Irkutsk',
        'place' => 'Бар «Рудник»',
        'cost' => 500,
        'currency' => 'RUB',
        'teams' => $teams,
    ];
}

test('rejects requests with a wrong secret or when integration is not configured', function () {
    rudaPost('eventCreated', ['id' => RUDA_RECORD, 'name' => 'X', 'eventDays' => []], 'wrong')->assertNotFound();

    config(['services.ruda.webhook_secret' => null]);
    rudaPost('eventCreated', ['id' => RUDA_RECORD, 'name' => 'X', 'eventDays' => []], '')->assertNotFound();

    expect(RudaWebhookCall::count())->toBe(0);
});

test('eventCreated creates games for every event day and logs the call', function () {
    rudaPost('eventCreated', [
        'id' => RUDA_RECORD,
        'name' => 'Классическая #120',
        'currency' => 'RUB',
        'eventDays' => [rudaDay(RUDA_DAY_1, '2026-10-10 19:00'), rudaDay(RUDA_DAY_2, '2026-10-17 19:00')],
    ])->assertNoContent();

    expect(Game::count())->toBe(2);

    $game = Game::where('ruda_event_day_id', RUDA_DAY_1)->first();
    expect($game->name)->toBe('Классическая #120');
    expect($game->played_at->format('Y-m-d H:i'))->toBe('2026-10-10 19:00');
    expect($game->venue)->toBe('Бар «Рудник»');
    expect((float) $game->cost)->toBe(500.0);
    expect($game->ruda_event_record_id)->toBe(RUDA_RECORD);
    expect($game->product->name)->toBe('Мозгобойня');

    expect(RudaWebhookCall::first()->status)->toBe('processed');
});

test('eventCreated reuses product/category of an existing game with the same name', function () {
    $product = Product::create(['name' => 'Квизмашина']);
    $category = GameCategory::create(['product_id' => $product->id, 'name' => 'Классическая 24']);
    Game::create([
        'product_id' => $product->id, 'category_id' => $category->id,
        'name' => 'Классическая #120', 'played_at' => '2026-01-01 19:00:00',
    ]);

    rudaPost('eventCreated', [
        'id' => RUDA_RECORD, 'name' => 'Классическая #120', 'currency' => 'RUB',
        'eventDays' => [rudaDay(RUDA_DAY_1, '2026-10-10 19:00')],
    ])->assertNoContent();

    $game = Game::where('ruda_event_day_id', RUDA_DAY_1)->first();
    expect($game->product_id)->toBe($product->id);
    expect($game->category_id)->toBe($category->id);
});

test('eventCreated updates changed days and removes days dropped from the series', function () {
    $payload = [
        'id' => RUDA_RECORD, 'name' => 'Классическая #120', 'currency' => 'RUB',
        'eventDays' => [rudaDay(RUDA_DAY_1, '2026-10-10 19:00'), rudaDay(RUDA_DAY_2, '2026-10-17 19:00')],
    ];
    rudaPost('eventCreated', $payload)->assertNoContent();

    $payload['eventDays'] = [rudaDay(RUDA_DAY_1, '2026-10-11 20:00', 600)];
    rudaPost('eventCreated', $payload)->assertNoContent();

    expect(Game::count())->toBe(1);
    $game = Game::first();
    expect($game->played_at->format('Y-m-d H:i'))->toBe('2026-10-11 20:00');
    expect((float) $game->cost)->toBe(600.0);
});

test('eventCreated keeps a dropped day if it already has participations', function () {
    rudaPost('eventCreated', [
        'id' => RUDA_RECORD, 'name' => 'Классическая #120', 'currency' => 'RUB',
        'eventDays' => [rudaDay(RUDA_DAY_1, '2026-10-10 19:00')],
    ]);
    $team = Team::create(['phone' => '79001112233', 'current_name' => 'Альфа']);
    GameParticipation::create([
        'game_id' => Game::first()->id, 'team_id' => $team->id,
        'team_name_at_time' => 'Альфа', 'players_count' => 5, 'revenue' => 2500,
    ]);

    rudaPost('eventCreated', [
        'id' => RUDA_RECORD, 'name' => 'Классическая #120', 'currency' => 'RUB', 'eventDays' => [],
    ])->assertNoContent();

    expect(Game::count())->toBe(1);
});

test('eventCreated links to a game previously imported from Excel instead of duplicating', function () {
    $product = Product::create(['name' => 'Мозгобойня']);
    $category = GameCategory::create(['product_id' => $product->id, 'name' => 'Классическая 24']);
    $existing = Game::create([
        'product_id' => $product->id, 'category_id' => $category->id,
        'name' => 'Классическая #120', 'played_at' => '2026-10-10 19:00:00',
    ]);

    rudaPost('eventCreated', [
        'id' => RUDA_RECORD, 'name' => 'Классическая #120', 'currency' => 'RUB',
        'eventDays' => [rudaDay(RUDA_DAY_1, '2026-10-10 19:00')],
    ])->assertNoContent();

    expect(Game::count())->toBe(1);
    expect($existing->fresh()->ruda_event_day_id)->toBe(RUDA_DAY_1);
});

test('teamRegistered stores the application and links an existing team by phone', function () {
    $team = Team::create(['phone' => '79001112233', 'current_name' => 'Альфа']);

    rudaPost('teamRegistered', rudaRegistration(501, 'Альфа', '8 (900) 111-22-33'))->assertNoContent();

    $registration = RudaRegistration::first();
    expect($registration->play_application_id)->toBe(501);
    expect($registration->team_id)->toBe($team->id);
    expect($registration->game->ruda_event_day_id)->toBe(RUDA_DAY_1);

    // Повторная доставка того же вебхука не создаёт дубль
    rudaPost('teamRegistered', rudaRegistration(501, 'Альфа', '8 (900) 111-22-33'))->assertNoContent();
    expect(RudaRegistration::count())->toBe(1);
});

test('teamRegistered creates a new team right away for an unknown phone', function () {
    rudaPost('teamRegistered', rudaRegistration(502, 'Новички', '+7 900 555-66-77'))->assertNoContent();

    $team = Team::where('phone', '79005556677')->first();
    expect($team)->not->toBeNull();
    expect($team->current_name)->toBe('Новички');
    expect($team->captain_name)->toBe('Иван');
    expect($team->email)->toBe('ivan@example.com');
    expect(RudaRegistration::first()->team_id)->toBe($team->id);
});

test('teamRegistered ignores placeholder phones and matches old teams stored with 8', function () {
    rudaPost('teamRegistered', rudaRegistration(503, 'Тест', '+7(000)000-00-00'))->assertNoContent();
    expect(Team::count())->toBe(0);
    expect(RudaRegistration::first()->team_id)->toBeNull();

    // Excel-импорт мог сохранить телефон с 8 в начале
    $old = Team::create(['phone' => '89001112233', 'current_name' => 'Старожилы']);
    rudaPost('teamRegistered', rudaRegistration(504, 'Старожилы', '+7 900 111-22-33'))->assertNoContent();

    expect(Team::count())->toBe(1);
    expect(RudaRegistration::where('play_application_id', 504)->first()->team_id)->toBe($old->id);
});

test('teamRegistered validates required fields', function () {
    rudaPost('teamRegistered', ['eventId' => RUDA_DAY_1])->assertStatus(422);

    expect(RudaWebhookCall::first()->status)->toBe('failed');
});

test('statisticsSubmitted creates participations, new teams and remembers the Ruda team id', function () {
    $existing = Team::create(['phone' => '79001112233', 'current_name' => 'Альфа']);

    rudaPost('teamRegistered', rudaRegistration(501, 'Альфа', '89001112233'));
    rudaPost('teamRegistered', rudaRegistration(502, 'Новички', '+7 900 555-66-77'));

    rudaPost('statisticsSubmitted', rudaStatistics([
        ['teamId' => 10, 'name' => 'Альфа', 'declaredPlayerCount' => 6, 'actualPlayerCount' => 5, 'points' => 42.5, 'revenue' => 2500],
        ['teamId' => 11, 'name' => 'новички', 'declaredPlayerCount' => 4, 'actualPlayerCount' => 4, 'points' => 30, 'revenue' => 2000],
        ['teamId' => null, 'name' => 'Без заявки', 'declaredPlayerCount' => 3, 'actualPlayerCount' => 3, 'points' => null, 'revenue' => 1500],
    ]))->assertNoContent();

    expect(GameParticipation::count())->toBe(2);

    $alpha = GameParticipation::where('team_id', $existing->id)->first();
    expect($alpha->players_count)->toBe(5);
    expect((float) $alpha->revenue)->toBe(2500.0);
    expect($existing->fresh()->ruda_team_id)->toBe(10);

    $newTeam = Team::where('phone', '79005556677')->first();
    expect($newTeam)->not->toBeNull();
    expect($newTeam->current_name)->toBe('новички');
    expect($newTeam->captain_name)->toBe('Иван');
    expect($newTeam->ruda_team_id)->toBe(11);

    $call = RudaWebhookCall::where('event', 'statisticsSubmitted')->first();
    expect($call->result['unmatched'])->toBe(['Без заявки']);
});

test('statisticsSubmitted finds a team by Ruda team id on later games and is idempotent', function () {
    $team = Team::create(['phone' => '79001112233', 'current_name' => 'Альфа', 'ruda_team_id' => 10]);

    $stats = rudaStatistics([
        ['teamId' => 10, 'name' => 'Альфа 2.0', 'declaredPlayerCount' => 6, 'actualPlayerCount' => 6, 'points' => 50, 'revenue' => 3000],
    ], RUDA_DAY_2);

    rudaPost('statisticsSubmitted', $stats)->assertNoContent();
    rudaPost('statisticsSubmitted', $stats)->assertNoContent();

    expect(GameParticipation::count())->toBe(1);
    expect(GameParticipation::first()->team_id)->toBe($team->id);
    expect(GameParticipation::first()->team_name_at_time)->toBe('Альфа 2.0');
    expect($team->nameHistory()->where('name', 'Альфа 2.0')->exists())->toBeTrue();
    expect($team->fresh()->current_name)->toBe('Альфа 2.0');
});

test('statisticsSubmitted falls back to an unambiguous team name when there is no registration', function () {
    $known = Team::create(['phone' => '79001112233', 'current_name' => 'Вафли']);
    $renamed = Team::create(['phone' => '79001112244', 'current_name' => 'Новое имя']);
    TeamNameHistory::create(['team_id' => $renamed->id, 'name' => 'Шляпа Кунг Лао', 'used_at' => '2026-01-01 19:00:00']);
    Team::create(['phone' => '79001112255', 'current_name' => 'No name']);
    Team::create(['phone' => '79001112266', 'current_name' => 'No name']);

    rudaPost('statisticsSubmitted', rudaStatistics([
        ['teamId' => 209026, 'name' => 'Вафли', 'declaredPlayerCount' => 5, 'actualPlayerCount' => 5, 'points' => '40', 'revenue' => 2500],
        ['teamId' => 139603, 'name' => 'Шляпа Кунг Лао', 'declaredPlayerCount' => 6, 'actualPlayerCount' => 6, 'points' => '56.4', 'revenue' => 3000],
        ['teamId' => 139002, 'name' => 'No name', 'declaredPlayerCount' => 6, 'actualPlayerCount' => 6, 'points' => '52.8', 'revenue' => 3000],
    ]))->assertNoContent();

    expect(GameParticipation::count())->toBe(2);
    expect($known->fresh()->ruda_team_id)->toBe(209026);
    expect($renamed->fresh()->ruda_team_id)->toBe(139603);

    // Два «No name» — неоднозначно, не угадываем
    expect(RudaWebhookCall::first()->result['unmatched'])->toBe(['No name']);
});

test('game time is taken from parsedDate in the event timezone, not from date', function () {
    // Реальные данные Руды: date в UTC/МСК, parsedDate — местное время площадки без смещения
    rudaPost('statisticsSubmitted', [
        'eventId' => RUDA_DAY_1, 'eventName' => 'Кино и Сериалы #Легкая 3',
        'date' => '2026-10-07 11:00', 'parsedDate' => '2026-10-07T19:00:00', 'timezone' => 'Asia/Irkutsk',
        'place' => 'Бар «41»', 'cost' => 500, 'currency' => 'RUB', 'teams' => [],
    ])->assertNoContent();

    expect(Game::first()->played_at->format('Y-m-d H:i'))->toBe('2026-10-07 19:00');

    // parsedDate со смещением переводится в часовой пояс площадки
    rudaPost('teamRegistered', array_merge(rudaRegistration(700, 'Тест', null, RUDA_DAY_2), [
        'date' => '2026-10-13 14:00', 'parsedDate' => '2026-10-13T11:00:00+00:00', 'timezone' => 'Asia/Irkutsk',
    ]))->assertNoContent();

    expect(Game::where('ruda_event_day_id', RUDA_DAY_2)->first()->played_at->format('Y-m-d H:i'))->toBe('2026-10-13 19:00');
});

test('ruda:reprocess replays stored webhooks after a fix', function () {
    rudaPost('statisticsSubmitted', rudaStatistics([
        ['teamId' => 5, 'name' => 'Вафли', 'declaredPlayerCount' => 5, 'actualPlayerCount' => 5, 'points' => null, 'revenue' => 2500],
    ]))->assertNoContent();
    expect(GameParticipation::count())->toBe(0);

    // Команда появилась в CRM позже (например, после Excel-импорта)
    Team::create(['phone' => '79001112233', 'current_name' => 'Вафли']);

    $this->artisan('ruda:reprocess', ['ids' => [RudaWebhookCall::first()->id]])->assertSuccessful();

    expect(GameParticipation::count())->toBe(1);
    expect(RudaWebhookCall::first()->result['unmatched'])->toBe([]);
});

test('game page lists Ruda registrations before the results arrive', function () {
    $team = Team::create(['phone' => '79001112233', 'current_name' => 'Альфа']);

    rudaPost('teamRegistered', rudaRegistration(501, 'Альфа', '89001112233'))->assertNoContent();
    rudaPost('teamRegistered', rudaRegistration(502, 'Тест', '+7(000)000-00-00'))->assertNoContent();

    $game = Game::where('ruda_event_day_id', RUDA_DAY_1)->first();

    Livewire\Livewire::actingAs(User::factory()->create())
        ->test(GameShow::class, ['game' => $game])
        ->assertSee('Заявки из Руды (2)')
        ->assertSee('Тест')
        ->assertSee('+7(000)000-00-00')
        ->assertSee($team->current_name);
});

test('teams list shows new teams without games first', function () {
    $old = Team::create(['phone' => '79001112233', 'current_name' => 'Старая', 'last_game_at' => '2026-10-01 19:00:00']);
    $new = Team::create(['phone' => '79001112244', 'current_name' => 'Из заявки Руды']);

    $teams = Livewire\Livewire::actingAs(User::factory()->create())
        ->test(TeamsCrud::class)
        ->instance()->teams;

    expect($teams->first()->id)->toBe($new->id);
});
