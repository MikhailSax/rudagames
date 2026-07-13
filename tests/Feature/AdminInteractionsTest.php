<?php

use App\Livewire\Admin\CommunicationDrafts;
use App\Livewire\Admin\GameShow;
use App\Models\Communication;
use App\Models\Game;
use App\Models\GameCategory;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('sending a communication draft actually works end to end', function () {
    // Это напрямую проверяет фикс бага: раньше SmsSenderInterface нигде не был
    // привязан в контейнере, и отправка падала бы с ошибкой на этом самом шаге.
    $user = User::factory()->create();
    $team = Team::create([
        'phone' => '79001234567',
        'current_name' => 'Тестовая команда',
        'email' => 'team@example.com',
    ]);
    $draft = Communication::create([
        'team_id' => $team->id,
        'channel' => null,
        'goal' => 'вернуть',
        'status' => Communication::STATUS_DRAFT,
        'suggested_message_text' => 'Привет! Соскучились по вам.',
    ]);

    Livewire::actingAs($user)
        ->test(CommunicationDrafts::class)
        ->call('openDraft', $draft->id)
        ->set('channel', 'sms')
        ->set('messageText', 'Финальный текст сообщения')
        ->call('send')
        ->assertHasNoErrors();

    $draft->refresh();
    expect($draft->status)->toBe(Communication::STATUS_SENT);
    expect($draft->channel)->toBe('sms');
    expect($draft->message_text)->toBe('Финальный текст сообщения');
});

test('rejecting a communication draft works', function () {
    $user = User::factory()->create();
    $team = Team::create(['phone' => '79001234568', 'current_name' => 'Команда 2']);
    $draft = Communication::create([
        'team_id' => $team->id,
        'goal' => 'напомнить',
        'status' => Communication::STATUS_DRAFT,
        'suggested_message_text' => 'Скоро игра!',
    ]);

    Livewire::actingAs($user)
        ->test(CommunicationDrafts::class)
        ->call('openDraft', $draft->id)
        ->call('reject');

    expect($draft->refresh()->status)->toBe(Communication::STATUS_REJECTED);
});

test('game finances can be entered and profit is calculated', function () {
    $user = User::factory()->create();
    $product = Product::create(['name' => 'Продукт']);
    $category = GameCategory::create(['product_id' => $product->id, 'name' => 'Категория']);
    $game = Game::create([
        'product_id' => $product->id,
        'category_id' => $category->id,
        'name' => 'Игра',
        'played_at' => now()->subDay(),
    ]);

    Livewire::actingAs($user)
        ->test(GameShow::class, ['game' => $game])
        ->call('openFinance')
        ->set('actual_revenue', '10000')
        ->set('actual_expenses', '4000')
        ->call('saveFinance')
        ->assertHasNoErrors();

    expect($game->refresh()->profit)->toEqual(6000.0);
});

test('editing a game persists changes', function () {
    $user = User::factory()->create();
    $product = Product::create(['name' => 'Продукт']);
    $category = GameCategory::create(['product_id' => $product->id, 'name' => 'Категория']);
    $game = Game::create([
        'product_id' => $product->id,
        'category_id' => $category->id,
        'name' => 'Старое название',
        'played_at' => now()->addDay(),
    ]);

    Livewire::actingAs($user)
        ->test(GameShow::class, ['game' => $game])
        ->call('openEdit')
        ->set('name', 'Новое название')
        ->set('venue', 'Новая площадка')
        ->call('saveEdit')
        ->assertHasNoErrors();

    expect($game->refresh()->name)->toBe('Новое название');
    expect($game->venue)->toBe('Новая площадка');
});

test('deleting a game redirects to the games list', function () {
    $user = User::factory()->create();
    $product = Product::create(['name' => 'Продукт']);
    $category = GameCategory::create(['product_id' => $product->id, 'name' => 'Категория']);
    $game = Game::create([
        'product_id' => $product->id,
        'category_id' => $category->id,
        'name' => 'Игра на удаление',
        'played_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(GameShow::class, ['game' => $game])
        ->call('delete')
        ->assertRedirect(route('admin.games'));

    expect(Game::find($game->id))->toBeNull();
});
