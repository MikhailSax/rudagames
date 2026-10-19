<?php

use App\Livewire\Admin\GameShow;
use App\Models\Game;
use App\Models\GameCategory;
use App\Models\GameParticipation;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

function rosterGame(): Game
{
    $product = Product::firstOrCreate(['name' => 'Мозгобойня']);
    $category = GameCategory::firstOrCreate(['product_id' => $product->id, 'name' => 'Легкая']);

    return Game::create([
        'product_id' => $product->id, 'category_id' => $category->id,
        'name' => 'Легкая #35', 'played_at' => '2026-10-13 19:00:00', 'cost' => 500,
    ]);
}

test('a team can be found, added to the game and its revenue counted', function () {
    $game = rosterGame();
    $alpha = Team::create(['phone' => '79001112233', 'current_name' => 'Альфа', 'captain_name' => 'Иван']);
    Team::create(['phone' => '79001112244', 'current_name' => 'Бета']);

    $component = Livewire::actingAs(User::factory()->create())
        ->test(GameShow::class, ['game' => $game])
        ->call('openAddTeam')
        ->set('teamSearch', 'альф')
        ->assertSee('Альфа')
        ->set('teamSearch', '1112233') // поиск по телефону
        ->assertSee('Альфа')
        ->call('selectTeam', $alpha->id)
        ->set('addPlayersCount', '6')
        ->assertSet('addRevenue', '3000') // 6 × 500
        ->call('addTeam')
        ->assertHasNoErrors();

    $participation = GameParticipation::first();
    expect($participation->team_id)->toBe($alpha->id);
    expect($participation->players_count)->toBe(6);
    expect((float) $participation->revenue)->toBe(3000.0);
    expect((float) $game->fresh()->actual_revenue)->toBe(3000.0);

    // Уже добавленная команда больше не предлагается
    expect($component->instance()->teamOptions->pluck('id')->all())->not->toContain($alpha->id);
});

test('a team cannot be added twice and a team must be chosen', function () {
    $game = rosterGame();
    $alpha = Team::create(['phone' => '79001112233', 'current_name' => 'Альфа']);
    GameParticipation::create([
        'game_id' => $game->id, 'team_id' => $alpha->id,
        'team_name_at_time' => 'Альфа', 'players_count' => 4, 'revenue' => 2000,
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(GameShow::class, ['game' => $game])
        ->call('openAddTeam')
        ->set('addPlayersCount', '4')
        ->call('addTeam')
        ->assertHasErrors('addTeamId')
        ->call('selectTeam', $alpha->id)
        ->call('addTeam')
        ->assertHasErrors('addTeamId');

    expect(GameParticipation::count())->toBe(1);
});

test('a team can be removed from the game and revenue is recalculated', function () {
    $game = rosterGame();
    $alpha = Team::create(['phone' => '79001112233', 'current_name' => 'Альфа']);
    $beta = Team::create(['phone' => '79001112244', 'current_name' => 'Бета']);
    $pAlpha = GameParticipation::create(['game_id' => $game->id, 'team_id' => $alpha->id, 'team_name_at_time' => 'Альфа', 'players_count' => 4, 'revenue' => 2000]);
    $pBeta = GameParticipation::create(['game_id' => $game->id, 'team_id' => $beta->id, 'team_name_at_time' => 'Бета', 'players_count' => 6, 'revenue' => 3000]);
    $game->syncRevenueFromItems();

    $component = Livewire::actingAs(User::factory()->create())
        ->test(GameShow::class, ['game' => $game->fresh()])
        ->call('removeParticipation', $pAlpha->id);

    expect(GameParticipation::count())->toBe(1);
    expect((float) $game->fresh()->actual_revenue)->toBe(3000.0);

    $component->call('removeParticipation', $pBeta->id);
    expect($game->fresh()->actual_revenue)->toBeNull();
    expect(Team::count())->toBe(2); // сами команды не удаляются
});
