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
 */
class FinanceSummaryService
{
    /**
     * @return array{
     *     income_total: float,
     *     expense_total: float,
     *     profit: float,
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

        return [
            'income_total' => $incomeTotal,
            'expense_total' => $expenseTotal,
            'profit' => $incomeTotal - $expenseTotal,
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
}
