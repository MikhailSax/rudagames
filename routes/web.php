<?php

use App\Livewire\Admin\AnalyticsDashboard;
use App\Livewire\Admin\ProductsCrud;
use App\Livewire\Admin\TeamsCrud;
use Illuminate\Support\Facades\Route;

// Внутренняя CRM — публичной страницы нет. Гость видит только логин,
// сотрудник сразу попадает в аналитику (замена дефолтному /dashboard).
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('admin.analytics')
        : redirect()->route('login');
})->name('home');

use App\Http\Controllers\TelegramWebhookController;

Route::post('/telegram/webhook', TelegramWebhookController::class)->name('telegram.webhook');

Route::get('/admin/teams', TeamsCrud::class)
    ->middleware('auth')
    ->name('admin.teams');

Route::get('/admin/analytics', AnalyticsDashboard::class)
    ->middleware(['auth'])
    ->name('admin.analytics');

use App\Livewire\Admin\BulkOutreach;

Route::get('/admin/outreach', BulkOutreach::class)
    ->middleware(['auth'])
    ->name('admin.outreach');

Route::get('/admin/products', ProductsCrud::class)
    ->middleware(['auth'])
    ->name('admin.products');

use App\Livewire\Admin\GamesCrud;

Route::get('/admin/games', GamesCrud::class)
    ->middleware(['auth'])
    ->name('admin.games');

use App\Livewire\Admin\GameShow;

Route::get('/admin/games/{game}', GameShow::class)
    ->middleware(['auth'])
    ->name('admin.games.show');

use App\Livewire\Admin\PlayersCrud;

Route::get('/admin/players', PlayersCrud::class)
    ->middleware(['auth'])
    ->name('admin.players');

use App\Livewire\Admin\DataImport;

Route::get('/admin/import', DataImport::class)
    ->middleware(['auth'])
    ->name('admin.import');

use App\Livewire\Admin\CommunicationDrafts;

Route::get('/admin/drafts', CommunicationDrafts::class)
    ->middleware(['auth'])
    ->name('admin.drafts');

use App\Livewire\Admin\WeeklyReports;

Route::get('/admin/reports', WeeklyReports::class)
    ->middleware(['auth'])
    ->name('admin.reports');

require __DIR__.'/settings.php';
