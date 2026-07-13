<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'month',
        'target_revenue',
    ];

    protected $casts = [
        'month' => 'date',
        'target_revenue' => 'decimal:2',
    ];
}
