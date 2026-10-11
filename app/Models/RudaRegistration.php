<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RudaRegistration extends Model
{
    protected $fillable = [
        'play_application_id',
        'ruda_event_day_id',
        'game_id',
        'team_id',
        'team_name',
        'registrant_name',
        'registrant_phone',
        'registrant_email',
        'captain_name',
        'captain_phone',
        'captain_email',
    ];

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
