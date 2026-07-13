<?php

use App\Models\Communication;
use App\Models\Game;
use App\Models\GameCategory;
use App\Models\GameParticipation;
use App\Models\Player;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;

/**
 * До этого теста ни одна страница админ-панели не была покрыта тестами вообще.
 * Здесь мы просто проверяем, что каждая страница рендерится без ошибок для
 * авторизованного пользователя — это ловит фатальные ошибки (неправильные
 * биндинги, отсутствующие View, сломанные Blade-директивы и т.п.), которые
 * статический анализ (`php -l`) не видит.
 */
function seedAdminFixtures(): array
{
    $product = Product::create(['name' => 'Квизмашина']);
    $category = GameCategory::create(['product_id' => $product->id, 'name' => 'Классика']);

    $game = Game::create([
        'product_id' => $product->id,
        'category_id' => $category->id,
        'name' => 'Тестовая игра',
        'played_at' => now()->addDay(),
        'venue' => 'Тестовая площадка',
        'cost' => 500,
    ]);

    $team = Team::create([
        'phone' => '79001234567',
        'current_name' => 'Тестовая команда',
        'captain_name' => 'Капитан',
        'email' => 'team@example.com',
        'lifecycle_stage' => 'новая',
        'activity_status' => 'активная',
    ]);

    GameParticipation::create([
        'game_id' => $game->id,
        'team_id' => $team->id,
        'team_name_at_time' => $team->current_name,
        'players_count' => 5,
        'revenue' => 1000,
    ]);

    Player::create(['name' => 'Игрок', 'phone' => '79007654321']);

    Communication::create([
        'team_id' => $team->id,
        'channel' => null,
        'goal' => 'вернуть',
        'status' => Communication::STATUS_DRAFT,
        'suggested_message_text' => 'Привет! Соскучились по вам.',
    ]);

    return compact('game', 'team');
}

test('every admin page renders for an authenticated user', function () {
    $user = User::factory()->create();
    ['game' => $game] = seedAdminFixtures();

    $this->actingAs($user);

    $pages = [
        'admin.analytics' => [],
        'admin.teams' => [],
        'admin.outreach' => [],
        'admin.drafts' => [],
        'admin.products' => [],
        'admin.games' => [],
        'admin.players' => [],
        'admin.import' => [],
        'admin.reports' => [],
    ];

    foreach ($pages as $routeName => $params) {
        $this->get(route($routeName, $params))->assertOk();
    }

    $this->get(route('admin.games.show', $game->id))->assertOk();
});
