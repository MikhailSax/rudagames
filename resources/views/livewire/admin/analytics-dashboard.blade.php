@once
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
@endonce

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Аналитика</flux:heading>
        <flux:text variant="subtle">{{ now()->format('d.m.Y') }}</flux:text>
    </div>

    <flux:card class="space-y-4">
        <flux:heading size="md">Сегодня</flux:heading>
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <flux:text size="sm" variant="subtle">Новые регистрации</flux:text>
                <flux:heading size="lg">{{ $this->todayKpis['new_registrations_today'] }}</flux:heading>
            </div>
            <div>
                <flux:text size="sm" variant="subtle">Команд на этой неделе</flux:text>
                <flux:heading size="lg">{{ $this->todayKpis['teams_this_week'] }}</flux:heading>
            </div>
            <div>
                <flux:text size="sm" variant="subtle">Ожидаемая выручка</flux:text>
                <flux:heading size="lg">{{ number_format($this->todayKpis['expected_revenue'], 0, ',', ' ') }} ₽</flux:heading>
            </div>
            <div>
                <flux:text size="sm" variant="subtle">Нужно вернуть</flux:text>
                <flux:heading size="lg" class="text-amber-600 dark:text-amber-400">{{ $this->todayKpis['need_to_return'] }}</flux:heading>
            </div>
            <div>
                <flux:text size="sm" variant="subtle">SMS сегодня</flux:text>
                <flux:heading size="lg">{{ $this->todayKpis['sms_today'] }}</flux:heading>
            </div>
        </div>
    </flux:card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <flux:card class="space-y-3">
            <flux:heading size="md">Главные события</flux:heading>
            <ul class="space-y-2">
                <li class="flex items-center gap-2">
                    <flux:icon name="sparkles" class="w-4 h-4 text-blue-500 shrink-0" />
                    <flux:text size="sm">{{ $this->mainEvents['new_teams'] }} новых команд за последние 7 дней.</flux:text>
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon name="trophy" class="w-4 h-4 text-purple-500 shrink-0" />
                    <flux:text size="sm">{{ $this->mainEvents['milestones_reached'] }} команд достигли нового уровня и ждут поздравления.</flux:text>
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon name="phone" class="w-4 h-4 text-amber-500 shrink-0" />
                    <flux:text size="sm">{{ $this->mainEvents['need_contact'] }} команд требуют контакта — см. «Черновики рассылок».</flux:text>
                </li>
                <li class="flex items-center gap-2">
                    <flux:icon name="arrow-path" class="w-4 h-4 text-teal-500 shrink-0" />
                    <flux:text size="sm">{{ $this->mainEvents['need_second_game'] }} новых команд стоит довести до второй игры.</flux:text>
                </li>
            </ul>
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading size="md">Цель месяца</flux:heading>
            @if ($this->currentMonthGoal)
                <div class="space-y-2">
                    <div class="flex items-baseline justify-between">
                        <flux:text size="sm" variant="subtle">
                            {{ number_format($this->currentMonthRevenue, 0, ',', ' ') }} ₽ из {{ number_format($this->currentMonthGoal->target_revenue, 0, ',', ' ') }} ₽
                        </flux:text>
                        <flux:text size="sm" class="font-semibold">{{ $this->monthGoalProgressPercent }}%</flux:text>
                    </div>
                    <div class="w-full bg-zinc-100 dark:bg-zinc-800 rounded-full h-2 overflow-hidden">
                        <div class="h-full bg-green-500" style="width: {{ $this->monthGoalProgressPercent }}%"></div>
                    </div>
                </div>
            @else
                <flux:text size="sm" variant="subtle">Цель на этот месяц ещё не задана.</flux:text>
                <div class="flex gap-2">
                    <flux:input wire:model="goalInput" type="number" placeholder="Целевая выручка, ₽" class="flex-1" />
                    <flux:button wire:click="saveGoal" variant="primary">Задать</flux:button>
                </div>
                @error('goalInput') <flux:text size="sm" class="text-red-600">{{ $message }}</flux:text> @enderror
            @endif
        </flux:card>
    </div>

    <flux:text variant="subtle">За последние 30 дней</flux:text>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="space-y-1">
            <flux:text size="sm" variant="subtle">Выручка</flux:text>
            <flux:heading size="lg">{{ number_format($this->kpis['revenue'], 0, ',', ' ') }} ₽</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm" variant="subtle">Игр сыграно</flux:text>
            <flux:heading size="lg">{{ $this->kpis['games_played'] }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm" variant="subtle">Средний размер команды</flux:text>
            <flux:heading size="lg">{{ $this->kpis['avg_team_size'] }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm" variant="subtle">Новые команды</flux:text>
            <flux:heading size="lg">{{ $this->kpis['new_teams'] }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm" variant="subtle">Активных команд</flux:text>
            <flux:heading size="lg" class="text-green-600 dark:text-green-400">{{ $this->kpis['active_teams'] }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm" variant="subtle">Спящих команд</flux:text>
            <flux:heading size="lg" class="text-zinc-500">{{ $this->kpis['dormant_teams'] }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm" variant="subtle">Потерянных команд</flux:text>
            <flux:heading size="lg" class="text-red-600 dark:text-red-400">{{ $this->kpis['lost_teams'] }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm" variant="subtle">Черновиков ждут</flux:text>
            <flux:heading size="lg" class="text-blue-600 dark:text-blue-400">{{ $this->kpis['pending_drafts'] }}</flux:heading>
        </flux:card>
    </div>

    @php $finance = $this->finance; @endphp
    <flux:card class="space-y-5">
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
            <div>
                <flux:heading size="md">Доходы и расходы</flux:heading>
                <flux:text size="sm" variant="subtle">Билеты из Руды, доходы и расходы по категориям за выбранный период</flux:text>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <flux:input wire:model.live="financeFrom" type="date" label="С" />
                <flux:input wire:model.live="financeTo" type="date" label="По" />
                <flux:button size="sm" variant="ghost" wire:click="setFinancePeriod('month')">Этот месяц</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="setFinancePeriod('prev_month')">Прошлый месяц</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="setFinancePeriod('quarter')">Квартал</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="setFinancePeriod('year')">Год</flux:button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <flux:text size="sm" variant="subtle">Доходы</flux:text>
                <flux:heading size="xl">{{ number_format($finance['income_total'], 0, ',', ' ') }} ₽</flux:heading>
            </div>
            <div>
                <flux:text size="sm" variant="subtle">Расходы</flux:text>
                <flux:heading size="xl">{{ number_format($finance['expense_total'], 0, ',', ' ') }} ₽</flux:heading>
            </div>
            <div>
                <flux:text size="sm" variant="subtle">Прибыль</flux:text>
                <flux:heading size="xl" class="{{ $finance['profit'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ number_format($finance['profit'], 0, ',', ' ') }} ₽
                </flux:heading>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 border-t border-zinc-100 dark:border-zinc-800 pt-4">
            @foreach (['Доходы' => $finance['income_groups'], 'Расходы' => $finance['expense_groups']] as $title => $groups)
                <div class="space-y-4">
                    <flux:heading size="sm">{{ $title }}</flux:heading>
                    @foreach ($groups as $group)
                        <div class="space-y-1">
                            <div class="flex items-center justify-between gap-2">
                                <flux:text class="font-medium">{{ $group['label'] }}</flux:text>
                                <flux:text class="font-medium">{{ number_format($group['total'], 0, ',', ' ') }} ₽</flux:text>
                            </div>
                            @forelse ($group['rows'] as $row)
                                <div class="flex items-center justify-between gap-2 pl-4">
                                    <flux:text size="sm" variant="subtle">{{ $row['name'] }}</flux:text>
                                    <flux:text size="sm" variant="subtle">{{ number_format($row['amount'], 0, ',', ' ') }} ₽</flux:text>
                                </div>
                            @empty
                                <flux:text size="sm" variant="subtle" class="pl-4">нет за период</flux:text>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </flux:card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <flux:card>
            <flux:heading size="md" class="mb-4">Выручка по месяцам</flux:heading>
            <canvas
                id="revenue-chart"
                wire:ignore
                x-data
                x-init="
                    const ctx = $el.getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: @js(array_keys($this->revenueByMonth)),
                            datasets: [{
                                label: 'Выручка, ₽',
                                data: @js(array_values($this->revenueByMonth)),
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.3,
                                fill: true,
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true } }
                        }
                    });
                "
            ></canvas>
        </flux:card>

        <flux:card>
            <flux:heading size="md" class="mb-4">Топ продуктов по числу игр</flux:heading>
            <canvas
                id="products-chart"
                wire:ignore
                x-data
                x-init="
                    const ctx = $el.getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: @js(array_keys($this->topProducts)),
                            datasets: [{
                                label: 'Игр сыграно',
                                data: @js(array_values($this->topProducts)),
                                backgroundColor: '#8b5cf6',
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true } }
                        }
                    });
                "
            ></canvas>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <flux:card>
            <flux:heading size="md" class="mb-4">Команды по стадии жизненного цикла</flux:heading>
            <div class="space-y-3">
                @foreach (['новая', 'развивающаяся', 'постоянная', 'ядро сообщества'] as $stage)
                    @php $count = $this->lifecycleBreakdown[$stage] ?? 0; @endphp
                    <div class="flex items-center gap-3">
                        <flux:text class="w-40 shrink-0">{{ ucfirst($stage) }}</flux:text>
                        <div class="flex-1 bg-zinc-100 dark:bg-zinc-800 rounded-full h-2 overflow-hidden">
                            <div
                                class="h-full bg-purple-500"
                                style="width: {{ array_sum($this->lifecycleBreakdown) > 0 ? ($count / array_sum($this->lifecycleBreakdown) * 100) : 0 }}%"
                            ></div>
                        </div>
                        <flux:text class="w-10 text-right">{{ $count }}</flux:text>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="md" class="mb-4">Команды по статусу активности</flux:heading>
            <div class="space-y-3">
                @foreach (['активная' => 'bg-green-500', 'остывающая' => 'bg-amber-500', 'спящая' => 'bg-zinc-400', 'потерянная' => 'bg-red-500'] as $status => $color)
                    @php $count = $this->activityBreakdown[$status] ?? 0; @endphp
                    <div class="flex items-center gap-3">
                        <flux:text class="w-40 shrink-0">{{ ucfirst($status) }}</flux:text>
                        <div class="flex-1 bg-zinc-100 dark:bg-zinc-800 rounded-full h-2 overflow-hidden">
                            <div
                                class="h-full {{ $color }}"
                                style="width: {{ array_sum($this->activityBreakdown) > 0 ? ($count / array_sum($this->activityBreakdown) * 100) : 0 }}%"
                            ></div>
                        </div>
                        <flux:text class="w-10 text-right">{{ $count }}</flux:text>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </div>
</div>
