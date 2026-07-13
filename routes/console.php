<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Модуль 3/5: ежедневный пересчёт агрегатов и Marketing Profile команд
Schedule::command('teams:recalculate-profiles')->dailyAt('03:00');

// Модуль 4: ежедневный пересчёт games_count у игроков
Schedule::command('players:recalculate-stats')->dailyAt('03:15');

// Модуль 6: ежедневно создаёт черновики предложений контакта — руководитель подтверждает
// отправку вручную на странице «Черновики рассылок» (запускается после пересчёта профилей).
Schedule::command('marketing:run-engine')->dailyAt('04:00');

// Модуль 8: еженедельный отчёт за прошлую неделю (без AI-выводов)
Schedule::command('reports:generate-weekly')->weeklyOn(1, '05:00');

