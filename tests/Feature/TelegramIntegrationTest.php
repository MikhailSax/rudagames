<?php

use App\Livewire\Admin\BulkOutreach;
use App\Models\Communication;
use App\Models\Team;
use App\Models\User;
use App\Services\Messenger\TelegramMessengerSender;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

// --- Team model helpers ---

test('telegramLinkToken generates and persists a token once', function () {
    $team = Team::create(['phone' => '79001110001', 'current_name' => 'Команда А']);

    $token = $team->telegramLinkToken();

    expect($token)->not->toBeEmpty();
    expect($team->fresh()->telegram_link_token)->toBe($token);

    // Calling again returns the SAME token, doesn't regenerate.
    expect($team->telegramLinkToken())->toBe($token);
});

test('telegramInviteUrl is null without a configured bot username', function () {
    config(['services.telegram.bot_username' => null]);
    $team = Team::create(['phone' => '79001110002', 'current_name' => 'Команда Б']);

    expect($team->telegramInviteUrl())->toBeNull();
});

test('telegramInviteUrl builds a valid t.me deep link when bot username is configured', function () {
    config(['services.telegram.bot_username' => 'RudaGamesBot']);
    $team = Team::create(['phone' => '79001110003', 'current_name' => 'Команда В']);

    $url = $team->telegramInviteUrl();

    expect($url)->toStartWith('https://t.me/RudaGamesBot?start=');
    expect($url)->toContain($team->fresh()->telegram_link_token);
});

test('isTelegramLinked reflects whether a chat_id is set', function () {
    $team = Team::create(['phone' => '79001110004', 'current_name' => 'Команда Г']);
    expect($team->isTelegramLinked())->toBeFalse();

    $team->update(['telegram_chat_id' => '555']);
    expect($team->fresh()->isTelegramLinked())->toBeTrue();
});

// --- Webhook: linking a team via /start <token> ---

test('webhook links a team when given a valid start token', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    config(['services.telegram.bot_token' => 'test-token', 'services.telegram.webhook_secret' => null]);

    $team = Team::create(['phone' => '79001110005', 'current_name' => 'Команда Д']);
    $token = $team->telegramLinkToken();

    $response = $this->postJson(route('telegram.webhook'), [
        'message' => [
            'chat' => ['id' => 987654],
            'text' => "/start {$token}",
        ],
    ]);

    $response->assertNoContent();

    $team->refresh();
    expect($team->telegram_chat_id)->toBe('987654');
    expect($team->telegram_linked_at)->not->toBeNull();
});

test('webhook ignores an unknown or expired start token without crashing', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    config(['services.telegram.bot_token' => 'test-token']);

    $response = $this->postJson(route('telegram.webhook'), [
        'message' => [
            'chat' => ['id' => 111],
            'text' => '/start does-not-exist',
        ],
    ]);

    $response->assertNoContent();
    expect(Team::where('telegram_chat_id', '111')->exists())->toBeFalse();
});

test('webhook handles a bare /start with no token gracefully', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    config(['services.telegram.bot_token' => 'test-token']);

    $response = $this->postJson(route('telegram.webhook'), [
        'message' => [
            'chat' => ['id' => 222],
            'text' => '/start',
        ],
    ]);

    $response->assertNoContent();
});

test('webhook rejects requests with a wrong secret token when a secret is configured', function () {
    config(['services.telegram.webhook_secret' => 'expected-secret']);

    $response = $this->postJson(
        route('telegram.webhook'),
        ['message' => ['chat' => ['id' => 1], 'text' => '/start x']],
        ['X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret']
    );

    $response->assertForbidden();
});

// --- TelegramMessengerSender ---

test('TelegramMessengerSender does not send without a bot token', function () {
    $sender = new TelegramMessengerSender(null);

    expect($sender->send('79001110006', 'привет'))->toBeFalse();
});

test('TelegramMessengerSender does not send to a team that has not linked Telegram', function () {
    Team::create(['phone' => '79001110007', 'current_name' => 'Команда Е']);
    $sender = new TelegramMessengerSender('test-token');

    expect($sender->send('79001110007', 'привет'))->toBeFalse();
});

test('TelegramMessengerSender sends successfully to a linked team', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    Team::create([
        'phone' => '79001110008',
        'current_name' => 'Команда Ж',
        'telegram_chat_id' => '424242',
    ]);

    $sender = new TelegramMessengerSender('test-token');

    expect($sender->send('79001110008', 'привет'))->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
            && $request['chat_id'] === '424242'
            && $request['text'] === 'привет';
    });
});

test('TelegramMessengerSender returns false when Telegram responds with an error', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 400)]);
    Team::create([
        'phone' => '79001110009',
        'current_name' => 'Команда З',
        'telegram_chat_id' => '999',
    ]);

    $sender = new TelegramMessengerSender('test-token');

    expect($sender->send('79001110009', 'привет'))->toBeFalse();
});

// --- BulkOutreach messenger channel ---

test('bulk outreach messenger channel skips teams without Telegram and sends to linked ones', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    config(['services.telegram.bot_token' => 'test-token']);

    $linked = Team::create([
        'phone' => '79001110010',
        'current_name' => 'Привязана',
        'telegram_chat_id' => '111222',
    ]);
    $unlinked = Team::create(['phone' => '79001110011', 'current_name' => 'Не привязана']);

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(BulkOutreach::class)
        ->set('channel', 'messenger')
        ->set('messageText', 'Привет, {name}!')
        ->call('send')
        ->assertHasNoErrors();

    expect(Communication::where('team_id', $linked->id)->where('status', Communication::STATUS_SENT)->exists())->toBeTrue();
    expect(Communication::where('team_id', $unlinked->id)->exists())->toBeFalse();
});
