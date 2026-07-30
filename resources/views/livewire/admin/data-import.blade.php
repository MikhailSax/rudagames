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

            <form wire:submit="import" class="space-y-4">
                <div class="rounded-xl border border-dashed border-zinc-300 p-4 dark:border-zinc-700">
                    <input
                        id="data-import-file"
                        class="sr-only"
                        type="file"
                        wire:model="file"
                        accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                    />

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <flux:text class="font-medium">Выберите Excel-файл для импорта</flux:text>
                            <flux:text size="sm" variant="subtle">Поддерживаются файлы .xlsx и .xls до 10 МБ.</flux:text>
                        </div>

                        <label
                            for="data-import-file"
                            class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-800 shadow-xs hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:hover:bg-zinc-700"
                        >
                            Выбрать файл
                        </label>
                    </div>
                </div>

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
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                        wire:target="file,import"
                        :disabled="! $file"
                    >
                        Импортировать
                    </flux:button>
                </div>
            </form>
        </flux:card>
    @endif
</div>
