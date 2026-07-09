<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Модуль 3/5: ежедневный пересчёт агрегатов и Marketing Profile команд
Schedule::command('teams:recalculate-profiles')->dailyAt('03:00');

