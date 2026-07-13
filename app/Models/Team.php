<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'current_name',
        'captain_name',
        'email',
        'telegram_chat_id',
        'telegram_link_token',
        'telegram_linked_at',
        'first_game_at',
        'last_game_at',
        'games_count',
        'total_revenue',
        'avg_team_size',
        'avg_interval_days',
        'lifecycle_stage',
        'activity_status',
        'ltv',
        'favorite_product_id',
    ];

    protected $casts = [
        'first_game_at' => 'datetime',
        'last_game_at' => 'datetime',
        'telegram_linked_at' => 'datetime',
        'total_revenue' => 'decimal:2',
        'avg_team_size' => 'decimal:2',
        'ltv' => 'decimal:2',
    ];

    public function participations(): HasMany
    {
        return $this->hasMany(GameParticipation::class);
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_participations')
            ->withPivot(['team_name_at_time', 'players_count', 'revenue'])
            ->withTimestamps();
    }

    public function nameHistory(): HasMany
    {
        return $this->hasMany(TeamNameHistory::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class);
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'team_player')
            ->withTimestamps();
    }

    public function favoriteProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'favorite_product_id');
    }

    // Нормализация телефона: храним только цифры (напр. 79021699021)
    public function setPhoneAttribute(string $value): void
    {
        $this->attributes['phone'] = preg_replace('/\D/', '', $value);
    }

    /**
     * Персональная ссылка команды на Telegram-бота. При первом обращении генерирует
     * токен — по нему вебхук бота свяжет chat_id с этой командой (Модуль 6: Telegram).
     */
    public function telegramLinkToken(): string
    {
        if (! $this->telegram_link_token) {
            $this->telegram_link_token = bin2hex(random_bytes(16));
            $this->save();
        }

        return $this->telegram_link_token;
    }

    public function telegramInviteUrl(): ?string
    {
        $botUsername = config('services.telegram.bot_username');

        if (! $botUsername) {
            return null;
        }

        return "https://t.me/{$botUsername}?start=".$this->telegramLinkToken();
    }

    public function isTelegramLinked(): bool
    {
        return ! empty($this->telegram_chat_id);
    }
}
