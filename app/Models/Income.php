<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Доход сверх билетов из Руды: по конкретной игре (бар, доп. продажи и т.п.)
 * или вне регулярных игр (выездные мероприятия, спонсорство, гранты).
 * Доходы по игре складываются в её выручку (Game::syncRevenueFromItems).
 */
class Income extends Model
{
    protected $fillable = [
        'income_category_id',
        'game_id',
        'amount',
        'received_on',
        'comment',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_on' => 'date',
    ];

    protected static function booted(): void
    {
        static::saved(function (Income $income) {
            self::syncGame($income->game_id);

            if ($income->wasChanged('game_id')) {
                self::syncGame($income->getOriginal('game_id'));
            }
        });

        static::deleted(fn (Income $income) => self::syncGame($income->game_id));
    }

    private static function syncGame(?int $gameId): void
    {
        if ($gameId) {
            Game::whereKey($gameId)->first()?->syncRevenueFromItems();
        }
    }

    /** @return BelongsTo<IncomeCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(IncomeCategory::class, 'income_category_id');
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Сумма доходов за период (даты включительно).
     * $unlinkedOnly — только доходы вне игр: доходы по играм уже сидят в actual_revenue игр.
     */
    public static function totalBetween(\DateTimeInterface $start, \DateTimeInterface $end, bool $unlinkedOnly = false): float
    {
        return (float) static::query()
            ->when($unlinkedOnly, fn ($q) => $q->whereNull('game_id'))
            ->whereBetween('received_on', [$start->format('Y-m-d'), $end->format('Y-m-d').' 23:59:59'])
            ->sum('amount');
    }
}
