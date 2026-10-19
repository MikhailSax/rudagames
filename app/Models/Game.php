<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'ruda_event_day_id',
        'ruda_event_record_id',
        'product_id',
        'category_id',
        'name',
        'played_at',
        'venue',
        'cost',
        'actual_revenue',
        'actual_expenses',
    ];

    protected $casts = [
        'played_at' => 'datetime',
        'cost' => 'decimal:2',
        'actual_revenue' => 'decimal:2',
        'actual_expenses' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GameCategory::class, 'category_id');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(GameParticipation::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'game_participations')
            ->withPivot(['team_name_at_time', 'players_count', 'revenue'])
            ->withTimestamps();
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Если по игре внесены расходы по статьям, actual_expenses = их сумма.
     * Последнюю статью удалили — сбрасываем в null, чтобы не осталась старая сумма.
     */
    public function syncExpensesFromItems(): void
    {
        $hasItems = $this->expenses()->exists();

        $this->update(['actual_expenses' => $hasItems ? $this->expenses()->sum('amount') : null]);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    /** Выручка игры складывается из билетов (участия из Руды) и доп. доходов по игре. */
    public function hasItemizedRevenue(): bool
    {
        return $this->participations()->exists() || $this->incomes()->exists();
    }

    /**
     * actual_revenue = билеты (сумма выручки команд) + доходы, привязанные к игре.
     * Если ни того, ни другого нет — оставляем то, что внесено вручную.
     */
    public function syncRevenueFromItems(): void
    {
        if (! $this->hasItemizedRevenue()) {
            return;
        }

        $this->update([
            'actual_revenue' => (float) $this->participations()->sum('revenue') + (float) $this->incomes()->sum('amount'),
        ]);
    }

    // Модуль 2: прибыль после ручного внесения выручки/расходов
    public function getProfitAttribute(): ?float
    {
        if ($this->actual_revenue === null || $this->actual_expenses === null) {
            return null;
        }

        return (float) $this->actual_revenue - (float) $this->actual_expenses;
    }
}
