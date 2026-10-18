<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Разовый доход вне регулярных игр (выездная игра и т.п.). Входит в выручку
 * и прибыль в аналитике и недельных отчётах наравне с выручкой от команд.
 */
class Income extends Model
{
    protected $fillable = [
        'income_category_id',
        'amount',
        'received_on',
        'comment',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_on' => 'date',
    ];

    /** @return BelongsTo<IncomeCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(IncomeCategory::class, 'income_category_id');
    }

    /** Сумма доходов за период (даты включительно). */
    public static function totalBetween(\DateTimeInterface $start, \DateTimeInterface $end): float
    {
        return (float) static::query()
            ->whereBetween('received_on', [$start->format('Y-m-d'), $end->format('Y-m-d').' 23:59:59'])
            ->sum('amount');
    }
}
