<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Подкатегория доходов. Категория верхнего уровня — group (GROUPS).
 */
class IncomeCategory extends Model
{
    public const GROUP_GAME = 'game';

    public const GROUP_OTHER = 'other';

    public const GROUPS = [
        self::GROUP_GAME => 'Привязанные к регулярным играм',
        self::GROUP_OTHER => 'Не привязанные к регулярным играм',
    ];

    protected $fillable = [
        'group',
        'name',
    ];

    /** @return HasMany<Income, $this> */
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function groupLabel(): string
    {
        return self::GROUPS[$this->group] ?? $this->group;
    }
}
