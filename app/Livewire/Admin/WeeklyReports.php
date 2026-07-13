<?php

namespace App\Livewire\Admin;

use App\Models\WeeklyReport;
use App\Services\Reports\WeeklyReportService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class WeeklyReports extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    #[Computed]
    public function reports()
    {
        return WeeklyReport::query()
            ->orderByDesc('period_start')
            ->paginate(20);
    }

    public function generatePreviousWeek(WeeklyReportService $service): void
    {
        $service->generateForPreviousWeek();

        unset($this->reports);
    }

    public function render()
    {
        return view('livewire.admin.weekly-reports')
            ->layoutData(['title' => 'Еженедельные отчёты']);
    }
}
