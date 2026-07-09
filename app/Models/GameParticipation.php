<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameParticipation extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'team_id',
        'team_name_at_time',
        'players_count',
        'revenue',
    ];

    protected $casts = [
        'revenue' => 'decimal:2',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
