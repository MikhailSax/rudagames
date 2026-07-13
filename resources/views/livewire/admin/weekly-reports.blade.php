<div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Еженедельные отчёты</flux:heading>
        <flux:button
            wire:click="generatePreviousWeek"
            variant="primary"
            icon="document-plus"
            wire:confirm="Сформировать отчёт за прошлую календарную неделю?"
        >
            Сформировать за прошлую неделю
        </flux:button>
    </div>

    <flux:text variant="subtle">
        Отчёт формируется автоматически каждый понедельник в 05:00 за предыдущую неделю.
        Здесь можно посмотреть историю или сформировать отчёт вручную.
    </flux:text>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Период</flux:table.column>
            <flux:table.column>Выручка</flux:table.column>
            <flux:table.column>Прибыль</flux:table.column>
            <flux:table.column>Ср. чек</flux:table.column>
            <flux:table.column>Ср. размер команды</flux:table.column>
            <flux:table.column>Новые</flux:table.column>
            <flux:table.column>Повторные</flux:table.column>
            <flux:table.column>Потерянные</flux:table.column>
            <flux:table.column>Реактивированные</flux:table.column>
            <flux:table.column>SMS отправлено</flux:table.column>
            <flux:table.column>Конверсия SMS</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->reports as $report)
                <flux:table.row wire:key="report-{{ $report->id }}">
                    <flux:table.cell>
                        {{ $report->period_start->format('d.m') }} — {{ $report->period_end->format('d.m.Y') }}
                    </flux:table.cell>
                    <flux:table.cell>{{ number_format($report->revenue, 0, ',', ' ') }} ₽</flux:table.cell>
                    <flux:table.cell>{{ number_format($report->profit, 0, ',', ' ') }} ₽</flux:table.cell>
                    <flux:table.cell>{{ $report->avg_check !== null ? number_format($report->avg_check, 0, ',', ' ').' ₽' : '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $report->avg_team_size ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $report->new_teams_count }}</flux:table.cell>
                    <flux:table.cell>{{ $report->returning_teams_count }}</flux:table.cell>
                    <flux:table.cell>{{ $report->lost_teams_count }}</flux:table.cell>
                    <flux:table.cell>{{ $report->reactivated_teams_count }}</flux:table.cell>
                    <flux:table.cell>{{ $report->sms_sent_count }}</flux:table.cell>
                    <flux:table.cell>{{ $report->sms_conversion_count }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="11">
                        <flux:text variant="subtle">Отчётов пока нет.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->reports->links() }}
</div>
