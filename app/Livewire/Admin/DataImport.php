<?php

namespace App\Livewire\Admin;

use App\Services\Import\TeamsGamesImportService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Обмен данными с rudagames.com прямо из админки: руководитель выгружает
 * с сайта xlsx-отчёт по мероприятиям (зарегистрированные команды,
 * послеигровая статистика) и загружает его сюда — без доступа к серверу
 * и консольным командам.
 */
#[Layout('layouts.app')]
class DataImport extends Component
{
    use WithFileUploads;

    public $file = null;

    public bool $imported = false;
    public array $stats = [];
    public array $skipped = [];

    public function import(TeamsGamesImportService $importer): void
    {
        $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $result = $importer->importFromFile($this->file->getRealPath());

        $this->stats = $result['stats'];
        $this->skipped = $result['skipped'];
        $this->imported = true;

        $this->file = null;
    }

    public function resetImport(): void
    {
        $this->reset(['file', 'imported', 'stats', 'skipped']);
    }

    public function render()
    {
        return view('livewire.admin.data-import')
            ->layoutData(['title' => 'Обмен данными']);
    }
}
