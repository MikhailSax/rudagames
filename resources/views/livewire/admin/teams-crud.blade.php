<div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Команды</flux:heading>
        <flux:button wire:click="create" variant="primary" icon="plus">
            Добавить команду
        </flux:button>
    </div>

    <div class="flex flex-col sm:flex-row gap-3">
        <flux:input
            wire:model.live.debounce.400ms="search"
            icon="magnifying-glass"
            placeholder="Поиск по названию, телефону, капитану..."
            class="sm:w-1/2"
        />
        <flux:select wire:model.live="lifecycleFilter" placeholder="Все стадии" class="sm:w-1/4">
            <flux:select.option value="">Все стадии</flux:select.option>
            <flux:select.option value="новая">Новая</flux:select.option>
            <flux:select.option value="развивающаяся">Развивающаяся</flux:select.option>
            <flux:select.option value="постоянная">Постоянная</flux:select.option>
            <flux:select.option value="ядро сообщества">Ядро сообщества</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="activityFilter" placeholder="Все статусы" class="sm:w-1/4">
            <flux:select.option value="">Все статусы</flux:select.option>
            <flux:select.option value="активная">Активная</flux:select.option>
            <flux:select.option value="остывающая">Остывающая</flux:select.option>
            <flux:select.option value="спящая">Спящая</flux:select.option>
            <flux:select.option value="потерянная">Потерянная</flux:select.option>
        </flux:select>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Команда</flux:table.column>
            <flux:table.column>Телефон</flux:table.column>
            <flux:table.column>Игр</flux:table.column>
            <flux:table.column>Стадия</flux:table.column>
            <flux:table.column>Активность</flux:table.column>
            <flux:table.column>Выручка</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->teams as $team)
                <flux:table.row wire:key="team-{{ $team->id }}">
                    <flux:table.cell>
                        <div class="font-medium">{{ $team->current_name }}</div>
                        @if ($team->captain_name)
                            <flux:text size="sm" variant="subtle">{{ $team->captain_name }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $team->phone }}</flux:table.cell>
                    <flux:table.cell>{{ $team->games_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="{{ $this->lifecycleColor($team->lifecycle_stage) }}" size="sm">
                            {{ $team->lifecycle_stage ?? 'не рассчитано' }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="{{ $this->activityColor($team->activity_status) }}" size="sm">
                            {{ $team->activity_status ?? 'не рассчитано' }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ number_format($team->total_revenue, 0, ',', ' ') }} ₽</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2 justify-end">
                            <flux:button size="sm" variant="ghost" wire:click="edit({{ $team->id }})">
                                Изменить
                            </flux:button>
                            <flux:button
                                size="sm"
                                variant="ghost"
                                wire:click="delete({{ $team->id }})"
                                wire:confirm="Удалить команду «{{ $team->current_name }}»? Это также удалит ВСЮ её историю игр и коммуникаций. Действие необратимо."
                            >
                                Удалить
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">
                        <flux:text variant="subtle">Команды не найдены.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->teams->links() }}

    <flux:modal name="team-form" class="max-w-2xl">
        <div class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId ? 'Редактировать команду' : 'Новая команда' }}
            </flux:heading>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input wire:model="current_name" label="Название" />
                <flux:input wire:model="captain_name" label="Капитан" />
                <flux:input wire:model="phone" label="Телефон" placeholder="+7..." />
                <flux:input wire:model="email" label="Email" type="email" />

                <flux:select wire:model="favorite_product_id" label="Любимый продукт" placeholder="— не выбрано —" class="sm:col-span-2">
                    <flux:select.option value="">— не выбрано —</flux:select.option>
                    @foreach ($this->products as $product)
                        <flux:select.option value="{{ $product->id }}">{{ $product->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Отмена</flux:button>
                </flux:modal.close>
                <flux:button wire:click="save" variant="primary">Сохранить</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
