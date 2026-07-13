<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:button as="a" href="{{ route('admin.games') }}" wire:navigate variant="ghost" size="sm" icon="arrow-left" class="mb-2">
                К списку игр
            </flux:button>
            <flux:heading size="xl">{{ $game->name }}</flux:heading>
            <div class="flex items-center gap-2 mt-1">
                <flux:badge color="zinc" size="sm">{{ $game->product->name }}</flux:badge>
                <flux:badge color="zinc" size="sm">{{ $game->category->name }}</flux:badge>
                @if ($game->played_at->isFuture())
                    <flux:badge color="blue" size="sm">предстоящая</flux:badge>
                @else
                    <flux:badge color="teal" size="sm">прошедшая</flux:badge>
                @endif
            </div>
        </div>

        <div class="flex gap-2">
            <flux:button variant="ghost" wire:click="openEdit">Изменить</flux:button>
            <flux:button
                variant="ghost"
                wire:click="delete"
                wire:confirm="Удалить игру «{{ $game->name }}»? Это также удалит все связанные регистрации команд."
            >
                Удалить
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="space-y-1">
            <flux:text size="sm" variant="subtle">Дата и время</flux:text>
            <flux:heading size="lg">{{ $game->played_at->format('d.m.Y H:i') }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm" variant="subtle">Площадка</flux:text>
            <flux:heading size="lg">{{ $game->venue ?? '—' }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm" variant="subtle">Стоимость участия</flux:text>
            <flux:heading size="lg">{{ $game->cost !== null ? number_format($game->cost, 0, ',', ' ').' ₽' : '—' }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm" variant="subtle">Команд зарегистрировано</flux:text>
            <flux:heading size="lg">{{ $this->participations->count() }}</flux:heading>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="md">Финансы</flux:heading>
            <flux:button size="sm" variant="ghost" wire:click="openFinance">
                {{ $game->actual_revenue !== null ? 'Изменить' : 'Внести финансы' }}
            </flux:button>
        </div>

        @if ($game->actual_revenue !== null)
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <flux:text size="sm" variant="subtle">Выручка</flux:text>
                    <flux:heading size="lg">{{ number_format($game->actual_revenue, 0, ',', ' ') }} ₽</flux:heading>
                </div>
                <div>
                    <flux:text size="sm" variant="subtle">Расходы</flux:text>
                    <flux:heading size="lg">{{ number_format($game->actual_expenses, 0, ',', ' ') }} ₽</flux:heading>
                </div>
                <div>
                    <flux:text size="sm" variant="subtle">Прибыль</flux:text>
                    <flux:heading size="lg" class="text-green-600 dark:text-green-400">{{ number_format($game->profit, 0, ',', ' ') }} ₽</flux:heading>
                </div>
                <div>
                    <flux:text size="sm" variant="subtle">Средний чек</flux:text>
                    <flux:heading size="lg">{{ $this->avgCheck !== null ? number_format($this->avgCheck, 0, ',', ' ').' ₽' : '—' }}</flux:heading>
                </div>
                <div>
                    <flux:text size="sm" variant="subtle">Средний размер команды</flux:text>
                    <flux:heading size="lg">{{ $this->avgTeamSize ?? '—' }}</flux:heading>
                </div>
            </div>
        @else
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>Финансы по этой игре ещё не внесены.</flux:callout.text>
            </flux:callout>
        @endif
    </flux:card>

    <flux:card class="space-y-4">
        <flux:heading size="md">Команды, участвовавшие в игре</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Команда</flux:table.column>
                <flux:table.column>Капитан</flux:table.column>
                <flux:table.column>Телефон</flux:table.column>
                <flux:table.column>Название на момент игры</flux:table.column>
                <flux:table.column>Игроков</flux:table.column>
                <flux:table.column>Выручка</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->participations as $participation)
                    <flux:table.row wire:key="participation-{{ $participation->id }}">
                        <flux:table.cell>
                            <div class="font-medium">{{ $participation->team?->current_name ?? '— команда удалена —' }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text size="sm" variant="subtle">{{ $participation->team?->captain_name ?? '—' }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($participation->team?->phone)
                                <div class="flex items-center gap-2" x-data="{ copied: false }">
                                    <span>{{ $participation->team->phone }}</span>
                                    <button
                                        type="button"
                                        title="Скопировать номер"
                                        x-on:click="navigator.clipboard.writeText('{{ $participation->team->phone }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                        class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                                    >
                                        <flux:icon x-show="!copied" name="clipboard" class="w-4 h-4" />
                                        <flux:icon x-show="copied" x-cloak name="check" class="w-4 h-4 text-green-600" />
                                    </button>
                                </div>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text size="sm" variant="subtle">{{ $participation->team_name_at_time }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>{{ $participation->players_count }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($participation->revenue, 0, ',', ' ') }} ₽</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <flux:text variant="subtle">Для этой игры пока нет зарегистрированных команд.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="edit-game" class="max-w-2xl">
        <div class="space-y-6">
            <flux:heading size="lg">Редактировать игру</flux:heading>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:select wire:model.live="product_id" label="Продукт" placeholder="Выберите продукт">
                    <flux:select.option value="">Выберите продукт</flux:select.option>
                    @foreach ($this->products as $product)
                        <flux:select.option value="{{ $product->id }}">{{ $product->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="category_id" label="Категория" placeholder="Выберите категорию">
                    <flux:select.option value="">Выберите категорию</flux:select.option>
                    @foreach ($this->categoriesForSelectedProduct as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="name" label="Название игры" class="sm:col-span-2" />

                <flux:input wire:model="played_at" label="Дата и время" type="datetime-local" />
                <flux:input wire:model="venue" label="Площадка" />

                <flux:input wire:model="cost" label="Стоимость участия, ₽" type="number" step="0.01" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Отмена</flux:button>
                </flux:modal.close>
                <flux:button wire:click="saveEdit" variant="primary">Сохранить</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="finance-form" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">Финансы игры</flux:heading>
            <flux:text size="sm" variant="subtle">
                Вносится после проведения игры — на основе этих данных считается прибыль.
            </flux:text>

            <flux:input wire:model="actual_revenue" label="Фактическая выручка, ₽" type="number" step="0.01" />
            <flux:input wire:model="actual_expenses" label="Расходы, ₽" type="number" step="0.01" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Отмена</flux:button>
                </flux:modal.close>
                <flux:button wire:click="saveFinance" variant="primary">Сохранить</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
