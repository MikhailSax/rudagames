<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Communication extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'channel',
        'goal',
        'status',
        'message_text',
        'suggested_message_text',
        'sent_at',
        'approved_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    // Статусы жизненного цикла записи: черновик -> отправлено / отклонено
    public const STATUS_DRAFT = 'черновик';
    public const STATUS_SENT = 'отправлено';
    public const STATUS_REJECTED = 'отклонено';
    public const STATUS_ERROR = 'ошибка';

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeDrafts($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeGoal($query, string $goal)
    {
        return $query->where('goal', $goal);
    }
}
