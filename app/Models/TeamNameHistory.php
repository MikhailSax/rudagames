<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamNameHistory extends Model
{
    use HasFactory;

    protected $table = 'team_name_history';
    protected $fillable = [
        'team_id',
        'name',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
