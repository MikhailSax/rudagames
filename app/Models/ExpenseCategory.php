<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    public const GROUP_GAME = 'game';

    public const GROUP_FIXED = 'fixed';

    public const GROUP_VARIABLE = 'variable';

    public const GROUPS = [
        self::GROUP_GAME => 'Постоянные, привязанные к играм',
        self::GROUP_FIXED => 'Постоянные, не привязанные к играм',
        self::GROUP_VARIABLE => 'Переменные',
    ];

    protected $fillable = [
        'group',
        'name',
    ];

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function groupLabel(): string
    {
        return self::GROUPS[$this->group] ?? $this->group;
    }
}
