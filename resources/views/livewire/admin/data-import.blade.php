<div class="flex flex-col gap-6">
    <flux:heading size="xl">Обмен данными с rudagames.com</flux:heading>

    <flux:text variant="subtle">
        Выгрузите с сайта rudagames.com xlsx-отчёт по мероприятиям (зарегистрированные команды и
        послеигровая статистика) и загрузите его здесь — данные попадут в CRM автоматически,
        без обращения к разработчикам.
    </flux:text>

    @if ($imported)
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.heading>Импорт завершён</flux:callout.heading>
            <flux:callout.text>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mt-2">
                    <div>
                        <div class="text-2xl font-semibold">{{ $stats['processed'] ?? 0 }}</div>
                        <flux:text size="sm" variant="subtle">строк обработано</flux:text>
                    </div>
                    <div>
                        <div class="text-2xl font-semibold">{{ $stats['games_created'] ?? 0 }}</div>
                        <flux:text size="sm" variant="subtle">новых игр</flux:text>
                    </div>
                    <div>
                        <div class="text-2xl font-semibold">{{ $stats['teams_created'] ?? 0 }}</div>
                        <flux:text size="sm" variant="subtle">новых команд</flux:text>
                    </div>
                    <div>
                        <div class="text-2xl font-semibold">{{ $stats['teams_renamed'] ?? 0 }}</div>
                        <flux:text size="sm" variant="subtle">переименований команд</flux:text>
                    </div>
                    <div>
                        <div class="text-2xl font-semibold">{{ $stats['skipped'] ?? 0 }}</div>
                        <flux:text size="sm" variant="subtle">строк пропущено</flux:text>
                    </div>
                </div>
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button wire:click="resetImport">Загрузить ещё файл</flux:button>
            </x-slot>
        </flux:callout>

        @if (! empty($skipped))
            <flux:card class="space-y-3">
                <flux:heading size="md">Пропущенные строки</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Строка</flux:table.column>
                        <flux:table.column>Команда</flux:table.column>
                        <flux:table.column>Телефон</flux:table.column>
                        <flux:table.column>Причина</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($skipped as $row)
                            <flux:table.row>
                                <flux:table.cell>{{ $row['row'] }}</flux:table.cell>
                                <flux:table.cell>{{ $row['team'] }}</flux:table.cell>
                                <flux:table.cell>{{ $row['phone'] }}</flux:table.cell>
                                <flux:table.cell>{{ $row['reason'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @endif
    @else
        <flux:card class="space-y-4">
            <flux:heading size="md">Файл выгрузки (.xlsx)</flux:heading>

            <input type="file" wire:model="file" accept=".xlsx,.xls" />

            <div wire:loading wire:target="file">
                <flux:text size="sm" variant="subtle">Загрузка файла...</flux:text>
            </div>

            @error('file') <flux:text size="sm" class="text-red-600">{{ $message }}</flux:text> @enderror

            @if ($file)
                <flux:callout variant="secondary" icon="document">
                    <flux:callout.text>Выбран файл: {{ $file->getClientOriginalName() }}</flux:callout.text>
                </flux:callout>
            @endif

            <div class="flex justify-end">
                <flux:button
                    variant="primary"
                    wire:click="import"
                    wire:loading.attr="disabled"
                    wire:target="import"
                >
                    Импортировать
                </flux:button>
            </div>
        </flux:card>
    @endif
</div>
