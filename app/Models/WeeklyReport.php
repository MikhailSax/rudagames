<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeeklyReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_start',
        'period_end',
        'revenue',
        'profit',
        'avg_check',
        'avg_team_size',
        'new_teams_count',
        'returning_teams_count',
        'lost_teams_count',
        'reactivated_teams_count',
        'sms_sent_count',
        'sms_conversion_count',
        'ai_summary',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'revenue' => 'decimal:2',
        'profit' => 'decimal:2',
        'avg_check' => 'decimal:2',
        'avg_team_size' => 'decimal:2',
    ];
}
