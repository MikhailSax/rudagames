<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'expense_category_id',
        'game_id',
        'amount',
        'spent_on',
        'comment',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'spent_on' => 'date',
    ];

    protected static function booted(): void
    {
        // Расходы игры (Модуль 2) = сумма привязанных к ней расходов, если они есть —
        // так прибыль игры и недельные отчёты считаются по статьям автоматически.
        static::saved(function (Expense $expense) {
            self::syncGame($expense->game_id);

            if ($expense->wasChanged('game_id')) {
                self::syncGame($expense->getOriginal('game_id'));
            }
        });

        static::deleted(fn (Expense $expense) => self::syncGame($expense->game_id));
    }

    private static function syncGame(?int $gameId): void
    {
        if ($gameId) {
            Game::whereKey($gameId)->first()?->syncExpensesFromItems();
        }
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
