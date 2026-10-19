<?php

namespace App\Services\Reports;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Game;
use App\Models\GameParticipation;
use App\Models\Income;
use App\Models\IncomeCategory;
use Carbon\CarbonInterface;

/**
 * Доходы и расходы за произвольный период (страница «Аналитика»),
 * в разрезе категорий и подкатегорий.
 *
 * Доходы: билеты (выручка команд из Руды) + доходы по играм + доходы вне игр.
 * Расходы: по статьям всех трёх категорий + расходы игр, внесённые одной суммой
 * в «Финансах» игры (без статей) — чтобы старые игры не выпадали из расчёта.
 * Игры попадают в период по дате игры, остальное — по дате дохода/расхода.
 *
 * Роялти Руде (services.ruda.royalty_percent, 15%) считается с доходов по регулярным
 * играм (билеты + доходы по играм); доходы вне игр (выездные и т.п.) не облагаются.
 */
class FinanceSummaryService
{
    /**
     * @return array{
     *     income_total: float,
     *     expense_total: float,
     *     profit: float,
     *     royalty_percent: float,
     *     royalty_base: float,
     *     royalty: float,
     *     profit_after_royalty: float,
     *     income_groups: array<string, array{label: string, total: float, rows: array<int, array{name: string, amount: float}>}>,
     *     expense_groups: array<string, array{label: string, total: float, rows: array<int, array{name: string, amount: float}>}>
     * }
     */
    public function summarize(CarbonInterface $from, CarbonInterface $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $dateRange = [$from->toDateString(), $to->toDateString().' 23:59:59'];

        // --- Доходы ---
        $incomeGroups = [];
        foreach (IncomeCategory::GROUPS as $group => $label) {
            $incomeGroups[$group] = ['label' => $label, 'total' => 0.0, 'rows' => []];
        }

        $tickets = (float) GameParticipation::query()
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->whereBetween('games.played_at', [$from, $to])
            ->sum('game_participations.revenue');

        if ($tickets > 0) {
            $incomeGroups[IncomeCategory::GROUP_GAME]['rows'][] = ['name' => 'Билеты (из Руды)', 'amount' => $tickets];
        }

        $incomes = Income::query()
            ->join('income_categories', 'income_categories.id', '=', 'incomes.income_category_id')
            ->whereBetween('incomes.received_on', $dateRange)
            ->groupBy('income_categories.group', 'income_categories.name')
            ->orderBy('income_categories.name')
            ->selectRaw('income_categories.`group` as grp, income_categories.name as name, SUM(incomes.amount) as total')
            ->toBase()
            ->get();

        foreach ($incomes as $row) {
            if (isset($incomeGroups[$row->grp])) {
                $incomeGroups[$row->grp]['rows'][] = ['name' => (string) $row->name, 'amount' => (float) $row->total];
            }
        }

        // --- Расходы ---
        $expenseGroups = [];
        foreach (ExpenseCategory::GROUPS as $group => $label) {
            $expenseGroups[$group] = ['label' => $label, 'total' => 0.0, 'rows' => []];
        }

        $expenses = Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereBetween('expenses.spent_on', $dateRange)
            ->groupBy('expense_categories.group', 'expense_categories.name')
            ->orderBy('expense_categories.name')
            ->selectRaw('expense_categories.`group` as grp, expense_categories.name as name, SUM(expenses.amount) as total')
            ->toBase()
            ->get();

        foreach ($expenses as $row) {
            if (isset($expenseGroups[$row->grp])) {
                $expenseGroups[$row->grp]['rows'][] = ['name' => (string) $row->name, 'amount' => (float) $row->total];
            }
        }

        // Игры, где расходы внесены одной суммой, без статей
        $manualGameExpenses = (float) Game::query()
            ->whereBetween('played_at', [$from, $to])
            ->whereNotNull('actual_expenses')
            ->whereDoesntHave('expenses')
            ->sum('actual_expenses');

        if ($manualGameExpenses > 0) {
            $expenseGroups[ExpenseCategory::GROUP_GAME]['rows'][] = ['name' => 'Внесено в «Финансах» игры без статей', 'amount' => $manualGameExpenses];
        }

        // --- Итоги ---
        $incomeGroups = $this->withTotals($incomeGroups);
        $expenseGroups = $this->withTotals($expenseGroups);

        $incomeTotal = (float) array_sum(array_column($incomeGroups, 'total'));
        $expenseTotal = (float) array_sum(array_column($expenseGroups, 'total'));

        $royaltyPercent = (float) config('services.ruda.royalty_percent', 15);
        $royaltyBase = $incomeGroups[IncomeCategory::GROUP_GAME]['total'];
        $royalty = round($royaltyBase * $royaltyPercent / 100, 2);

        return [
            'income_total' => $incomeTotal,
            'expense_total' => $expenseTotal,
            'profit' => $incomeTotal - $expenseTotal,
            'royalty_percent' => $royaltyPercent,
            'royalty_base' => $royaltyBase,
            'royalty' => $royalty,
            'profit_after_royalty' => $incomeTotal - $expenseTotal - $royalty,
            'income_groups' => $incomeGroups,
            'expense_groups' => $expenseGroups,
        ];
    }

    /**
     * @param  array<string, array{label: string, total: float, rows: array<int, array{name: string, amount: float}>}>  $groups
     * @return array<string, array{label: string, total: float, rows: array<int, array{name: string, amount: float}>}>
     */
    private function withTotals(array $groups): array
    {
        return array_map(
            fn (array $group) => [...$group, 'total' => (float) array_sum(array_column($group['rows'], 'amount'))],
            $groups,
        );
    }

    /**
     * Детализация одной категории верхнего уровня за период: подкатегории с долями,
     * помесячная разбивка и список операций.
     *
     * @param  'income'|'expense'  $kind
     * @return array{
     *     label: string,
     *     total: float,
     *     rows: array<int, array{name: string, amount: float, share: float}>,
     *     months: array<string, array<string, float>>,
     *     entries: array<int, array{date: CarbonInterface, sub: string, title: string, note: string, amount: float}>
     * }
     */
    public function categoryDetail(string $kind, string $group, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $dateRange = [$from->toDateString(), $to->toDateString().' 23:59:59'];
        $entries = [];

        if ($kind === 'income') {
            $label = IncomeCategory::GROUPS[$group] ?? $group;

            if ($group === IncomeCategory::GROUP_GAME) {
                $games = Game::query()
                    ->whereBetween('played_at', [$from, $to])
                    ->withCount('participations')
                    ->withSum('participations', 'revenue')
                    ->whereHas('participations')
                    ->get();

                foreach ($games as $game) {
                    $entries[] = [
                        'date' => $game->played_at,
                        'sub' => 'Билеты (из Руды)',
                        'title' => $game->name,
                        'note' => $game->participations_count.' команд',
                        'amount' => (float) $game->participations_sum_revenue,
                    ];
                }
            }

            $incomes = Income::with(['category', 'game'])
                ->whereHas('category', fn ($q) => $q->where('group', $group))
                ->whereBetween('received_on', $dateRange)
                ->get();

            foreach ($incomes as $income) {
                $entries[] = [
                    'date' => $income->held_at ?? $income->received_on,
                    'sub' => $income->category->name,
                    'title' => $income->game->name ?? $income->venue ?? '',
                    'note' => trim(($income->contact_name ?? '').' '.($income->comment ?? '')),
                    'amount' => (float) $income->amount,
                ];
            }
        } else {
            $label = ExpenseCategory::GROUPS[$group] ?? $group;

            $expenses = Expense::with(['category', 'game'])
                ->whereHas('category', fn ($q) => $q->where('group', $group))
                ->whereBetween('spent_on', $dateRange)
                ->get();

            foreach ($expenses as $expense) {
                $entries[] = [
                    'date' => $expense->spent_on,
                    'sub' => $expense->category->name,
                    'title' => $expense->game->name ?? '',
                    'note' => $expense->comment ?? '',
                    'amount' => (float) $expense->amount,
                ];
            }

            if ($group === ExpenseCategory::GROUP_GAME) {
                $games = Game::query()
                    ->whereBetween('played_at', [$from, $to])
                    ->whereNotNull('actual_expenses')
                    ->whereDoesntHave('expenses')
                    ->get();

                foreach ($games as $game) {
                    $entries[] = [
                        'date' => $game->played_at,
                        'sub' => 'Внесено в «Финансах» игры без статей',
                        'title' => $game->name,
                        'note' => '',
                        'amount' => (float) $game->actual_expenses,
                    ];
                }
            }
        }

        usort($entries, fn ($a, $b) => $b['date'] <=> $a['date']);

        $total = (float) array_sum(array_column($entries, 'amount'));

        $bySub = [];
        $months = [];
        foreach ($entries as $entry) {
            $bySub[$entry['sub']] = ($bySub[$entry['sub']] ?? 0) + $entry['amount'];
            $month = $entry['date']->format('Y-m');
            $months[$month][$entry['sub']] = ($months[$month][$entry['sub']] ?? 0) + $entry['amount'];
        }
        arsort($bySub);
        ksort($months);

        $rows = [];
        foreach ($bySub as $name => $amount) {
            $rows[] = [
                'name' => (string) $name,
                'amount' => (float) $amount,
                'share' => $total > 0 ? round($amount / $total * 100, 1) : 0.0,
            ];
        }

        return [
            'label' => $label,
            'total' => $total,
            'rows' => $rows,
            'months' => $months,
            'entries' => $entries,
        ];
    }
}
