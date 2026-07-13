<div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Игры</flux:heading>
        <flux:button wire:click="create" variant="primary" icon="plus">
            Добавить игру
        </flux:button>
    </div>

    <div class="flex flex-col sm:flex-row gap-3">
        <flux:input
            wire:model.live.debounce.400ms="search"
            icon="magnifying-glass"
            placeholder="Поиск по названию игры..."
            class="sm:w-1/2"
        />
        <flux:select wire:model.live="productFilter" placeholder="Все продукты" class="sm:w-1/4">
            <flux:select.option value="">Все продукты</flux:select.option>
            @foreach ($this->products as $product)
                <flux:select.option value="{{ $product->id }}">{{ $product->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="timeFilter" class="sm:w-1/4">
            <flux:select.option value="all">Все игры</flux:select.option>
            <flux:select.option value="upcoming">Предстоящие</flux:select.option>
            <flux:select.option value="past">Прошедшие</flux:select.option>
        </flux:select>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Игра</flux:table.column>
            <flux:table.column>Продукт / категория</flux:table.column>
            <flux:table.column>Дата</flux:table.column>
            <flux:table.column>Команд</flux:table.column>
            <flux:table.column>Финансы</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->games as $game)
                <flux:table.row wire:key="game-{{ $game->id }}">
                    <flux:table.cell>
                        <a href="{{ route('admin.games.show', $game->id) }}" wire:navigate class="font-medium hover:underline">
                            {{ $game->name }}
                        </a>
                        @if ($game->venue)
                            <flux:text size="sm" variant="subtle">{{ $game->venue }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:text size="sm">{{ $game->product->name }}</flux:text>
                        <flux:text size="sm" variant="subtle">{{ $game->category->name }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $game->played_at->format('d.m.Y H:i') }}
                        @if ($game->played_at->isFuture())
                            <flux:badge color="blue" size="sm">скоро</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $game->participations_count }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($game->actual_revenue !== null)
                            <flux:text size="sm">
                                Прибыль: {{ number_format($game->profit, 0, ',', ' ') }} ₽
                            </flux:text>
                            @if ($game->participations_count > 0)
                                <flux:text size="sm" variant="subtle">
                                    Ср. чек: {{ number_format($game->actual_revenue / $game->participations_count, 0, ',', ' ') }} ₽
                                    · Ср. команда: {{ round($game->participations_avg_players_count, 1) }}
                                </flux:text>
                            @endif
                        @else
                            <flux:badge color="amber" size="sm">не внесены</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2 justify-end">
                            <flux:button as="a" href="{{ route('admin.games.show', $game->id) }}" wire:navigate size="sm" variant="ghost">
                                Подробнее
                            </flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="openFinance({{ $game->id }})">
                                Финансы
                            </flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="edit({{ $game->id }})">
                                Изменить
                            </flux:button>
                            <flux:button
                                size="sm"
                                variant="ghost"
                                wire:click="delete({{ $game->id }})"
                                wire:confirm="Удалить игру «{{ $game->name }}»? Это также удалит все связанные регистрации команд."
                            >
                                Удалить
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        <flux:text variant="subtle">Игры не найдены.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->games->links() }}

    <flux:modal name="game-form" class="max-w-2xl">
        <div class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId ? 'Редактировать игру' : 'Новая игра' }}
            </flux:heading>

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
                <flux:button wire:click="save" variant="primary">Сохранить</flux:button>
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
