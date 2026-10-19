<?php

namespace App\Livewire\Admin;

use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Services\Reports\FinanceSummaryService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Финансовая аналитика за период: сводка (доходы, расходы, 15% Руде, прибыль)
 * и отдельная вкладка на каждую категорию доходов и расходов.
 */
#[Layout('layouts.app')]
class FinanceAnalytics extends Component
{
    // summary | income-game | income-other | expense-game | expense-fixed | expense-variable
    #[Url]
    public string $tab = 'summary';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        if (! $this->parseDate($this->from) || ! $this->parseDate($this->to)) {
            $this->setPeriod('month');
        }

        if (! array_key_exists($this->tab, $this->tabs())) {
            $this->tab = 'summary';
        }
    }

    public function setPeriod(string $preset): void
    {
        [$from, $to] = match ($preset) {
            'prev_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [now()->startOfQuarter(), now()],
            'year' => [now()->startOfYear(), now()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }

    /** @return array<string, string> */
    public function tabs(): array
    {
        $tabs = ['summary' => 'Сводка'];

        foreach (IncomeCategory::GROUPS as $group => $label) {
            $tabs['income-'.$group] = 'Доходы: '.mb_strtolower($label);
        }

        foreach (ExpenseCategory::GROUPS as $group => $label) {
            $tabs['expense-'.$group] = 'Расходы: '.mb_strtolower($label);
        }

        return $tabs;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(): array
    {
        $from = $this->parseDate($this->from) ?? now()->startOfMonth();
        $to = $this->parseDate($this->to) ?? now();

        return $from->gt($to) ? [$to, $from] : [$from, $to];
    }

    private function parseDate(string $value): ?Carbon
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? Carbon::parse($value) : null;
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function summary(): array
    {
        return app(FinanceSummaryService::class)->summarize(...$this->period());
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function detail(): ?array
    {
        if ($this->tab === 'summary') {
            return null;
        }

        [$kind, $group] = explode('-', $this->tab, 2);

        return app(FinanceSummaryService::class)->categoryDetail($kind, $group, ...$this->period());
    }

    public function render()
    {
        return view('livewire.admin.finance-analytics')
            ->layoutData(['title' => 'Финансовая аналитика']);
    }
}
