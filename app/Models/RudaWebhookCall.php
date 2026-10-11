<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RudaWebhookCall extends Model
{
    protected $fillable = [
        'event',
        'payload',
        'status',
        'result',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
    ];
}
