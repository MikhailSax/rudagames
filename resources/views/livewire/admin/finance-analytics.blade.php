<div class="flex flex-col gap-6">
    @php
        $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₽';
        $monthNames = ['01' => 'Январь', '02' => 'Февраль', '03' => 'Март', '04' => 'Апрель', '05' => 'Май', '06' => 'Июнь',
            '07' => 'Июль', '08' => 'Август', '09' => 'Сентябрь', '10' => 'Октябрь', '11' => 'Ноябрь', '12' => 'Декабрь'];
        $monthLabel = fn (string $ym) => ($monthNames[substr($ym, 5, 2)] ?? $ym).' '.substr($ym, 0, 4);
    @endphp

    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Финансовая аналитика</flux:heading>
            <flux:text variant="subtle" class="mt-1">Доходы и расходы за период, с разбивкой по каждой категории</flux:text>
        </div>
        <div class="flex flex-wrap items-end gap-2">
            <flux:input wire:model.live="from" type="date" label="С" />
            <flux:input wire:model.live="to" type="date" label="По" />
            <flux:button size="sm" variant="ghost" wire:click="setPeriod('month')">Этот месяц</flux:button>
            <flux:button size="sm" variant="ghost" wire:click="setPeriod('prev_month')">Прошлый месяц</flux:button>
            <flux:button size="sm" variant="ghost" wire:click="setPeriod('quarter')">Квартал</flux:button>
            <flux:button size="sm" variant="ghost" wire:click="setPeriod('year')">Год</flux:button>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach ($this->tabs() as $key => $label)
            <flux:button size="sm" :variant="$tab === $key ? 'primary' : 'filled'" wire:click="$set('tab', '{{ $key }}')" wire:key="tab-{{ $key }}">
                {{ $label }}
            </flux:button>
        @endforeach
    </div>

    @if ($tab === 'summary')
        @php $s = $this->summary; @endphp

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <flux:card class="space-y-1">
                <flux:text size="sm" variant="subtle">Доходы</flux:text>
                <flux:heading size="lg">{{ $fmt($s['income_total']) }}</flux:heading>
            </flux:card>
            <flux:card class="space-y-1">
                <flux:text size="sm" variant="subtle">Расходы</flux:text>
                <flux:heading size="lg">{{ $fmt($s['expense_total']) }}</flux:heading>
            </flux:card>
            <flux:card class="space-y-1">
                <flux:text size="sm" variant="subtle">Прибыль</flux:text>
                <flux:heading size="lg">{{ $fmt($s['profit']) }}</flux:heading>
            </flux:card>
            <flux:card class="space-y-1">
                <flux:text size="sm" variant="subtle">Руде {{ rtrim(rtrim(number_format($s['royalty_percent'], 2, ',', ''), '0'), ',') }}%</flux:text>
                <flux:heading size="lg" class="text-amber-600 dark:text-amber-400">{{ $fmt($s['royalty']) }}</flux:heading>
                <flux:text size="sm" variant="subtle">с {{ $fmt($s['royalty_base']) }}, без выездных</flux:text>
            </flux:card>
            <flux:card class="space-y-1">
                <flux:text size="sm" variant="subtle">Итого после отчисления Руде</flux:text>
                <flux:heading size="lg" class="{{ $s['profit_after_royalty'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $fmt($s['profit_after_royalty']) }}
                </flux:heading>
            </flux:card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach (['income' => ['Доходы', $s['income_groups']], 'expense' => ['Расходы', $s['expense_groups']]] as $kind => [$title, $groups])
                <flux:card class="space-y-4">
                    <flux:heading size="md">{{ $title }}</flux:heading>
                    @foreach ($groups as $group => $data)
                        <div class="space-y-1">
                            <button type="button" wire:click="$set('tab', '{{ $kind }}-{{ $group }}')" class="w-full flex items-center justify-between gap-2 text-left hover:underline">
                                <flux:text class="font-medium">{{ $data['label'] }}</flux:text>
                                <flux:text class="font-medium">{{ $fmt($data['total']) }}</flux:text>
                            </button>
                            @forelse ($data['rows'] as $row)
                                <div class="flex items-center justify-between gap-2 pl-4">
                                    <flux:text size="sm" variant="subtle">{{ $row['name'] }}</flux:text>
                                    <flux:text size="sm" variant="subtle">{{ $fmt($row['amount']) }}</flux:text>
                                </div>
                            @empty
                                <flux:text size="sm" variant="subtle" class="pl-4">нет за период</flux:text>
                            @endforelse
                        </div>
                    @endforeach
                </flux:card>
            @endforeach
        </div>
    @else
        @php $d = $this->detail; $subs = array_column($d['rows'], 'name'); @endphp

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
            <flux:card class="space-y-1">
                <flux:text size="sm" variant="subtle">{{ $d['label'] }}</flux:text>
                <flux:heading size="lg">{{ $fmt($d['total']) }}</flux:heading>
            </flux:card>
            <flux:card class="space-y-1">
                <flux:text size="sm" variant="subtle">Операций</flux:text>
                <flux:heading size="lg">{{ count($d['entries']) }}</flux:heading>
            </flux:card>
            <flux:card class="space-y-1">
                <flux:text size="sm" variant="subtle">Подкатегорий</flux:text>
                <flux:heading size="lg">{{ count($d['rows']) }}</flux:heading>
            </flux:card>
        </div>

        <flux:card class="space-y-3">
            <flux:heading size="md">По подкатегориям</flux:heading>
            @forelse ($d['rows'] as $row)
                <div class="space-y-1">
                    <div class="flex items-center justify-between gap-2">
                        <flux:text>{{ $row['name'] }}</flux:text>
                        <flux:text>{{ $fmt($row['amount']) }} · {{ number_format($row['share'], 1, ',', '') }}%</flux:text>
                    </div>
                    <div class="h-1.5 rounded bg-zinc-100 dark:bg-zinc-800">
                        <div class="h-1.5 rounded bg-blue-500" style="width: {{ $row['share'] }}%"></div>
                    </div>
                </div>
            @empty
                <flux:text variant="subtle">За период ничего нет.</flux:text>
            @endforelse
        </flux:card>

        @if (count($d['months']) > 0)
            <flux:card class="space-y-3">
                <flux:heading size="md">По месяцам</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Месяц</flux:table.column>
                        @foreach ($subs as $sub)
                            <flux:table.column>{{ $sub }}</flux:table.column>
                        @endforeach
                        <flux:table.column>Итого</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($d['months'] as $month => $values)
                            <flux:table.row wire:key="month-{{ $month }}">
                                <flux:table.cell>{{ $monthLabel($month) }}</flux:table.cell>
                                @foreach ($subs as $sub)
                                    <flux:table.cell>{{ isset($values[$sub]) ? $fmt($values[$sub]) : '—' }}</flux:table.cell>
                                @endforeach
                                <flux:table.cell class="font-medium">{{ $fmt(array_sum($values)) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @endif

        <flux:card class="space-y-3">
            <flux:heading size="md">Операции</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Дата</flux:table.column>
                    <flux:table.column>Подкатегория</flux:table.column>
                    <flux:table.column>Игра / место</flux:table.column>
                    <flux:table.column>Примечание</flux:table.column>
                    <flux:table.column>Сумма</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($d['entries'] as $i => $entry)
                        <flux:table.row wire:key="entry-{{ $i }}">
                            <flux:table.cell>{{ $entry['date']->format('d.m.Y') }}</flux:table.cell>
                            <flux:table.cell>{{ $entry['sub'] }}</flux:table.cell>
                            <flux:table.cell>{{ $entry['title'] ?: '—' }}</flux:table.cell>
                            <flux:table.cell><flux:text size="sm" variant="subtle">{{ $entry['note'] }}</flux:text></flux:table.cell>
                            <flux:table.cell>{{ $fmt($entry['amount']) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5"><flux:text variant="subtle">За период операций нет.</flux:text></flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>
