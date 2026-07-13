<?php

use App\Livewire\Admin\ProductsCrud;
use App\Livewire\Admin\TeamsCrud;
use App\Livewire\Admin\AnalyticsDashboard;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

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

use App\Livewire\Admin\PlayersCrud;

Route::get('/admin/players', PlayersCrud::class)
    ->middleware(['auth'])
    ->name('admin.players');

use App\Livewire\Admin\DataImport;

Route::get('/admin/import', DataImport::class)
    ->middleware(['auth'])
    ->name('admin.import');

require __DIR__.'/settings.php';
