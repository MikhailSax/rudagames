<div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Игроки</flux:heading>
        <flux:button wire:click="create" variant="primary" icon="plus">
            Добавить игрока
        </flux:button>
    </div>

    <flux:input
        wire:model.live.debounce.400ms="search"
        icon="magnifying-glass"
        placeholder="Поиск по имени или телефону..."
        class="sm:w-1/2"
    />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Имя</flux:table.column>
            <flux:table.column>Телефон</flux:table.column>
            <flux:table.column>Команд</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->players as $player)
                <flux:table.row wire:key="player-{{ $player->id }}">
                    <flux:table.cell>{{ $player->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $player->phone ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $player->teams_count }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2 justify-end">
                            <flux:button size="sm" variant="ghost" wire:click="edit({{ $player->id }})">
                                Изменить
                            </flux:button>
                            <flux:button
                                size="sm"
                                variant="ghost"
                                wire:click="delete({{ $player->id }})"
                                wire:confirm="Удалить игрока «{{ $player->name ?? $player->phone }}»?"
                            >
                                Удалить
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">
                        <flux:text variant="subtle">Игроки не найдены.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->players->links() }}

    <flux:modal name="player-form" class="max-w-lg">
        <div class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId ? 'Редактировать игрока' : 'Новый игрок' }}
            </flux:heading>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input wire:model="name" label="Имя" />
                <flux:input wire:model="phone" label="Телефон" placeholder="+7..." />
            </div>

            <div>
                <flux:input
                    wire:model.live.debounce.300ms="teamSearch"
                    icon="magnifying-glass"
                    placeholder="Найти команду для привязки..."
                    label="Команды игрока"
                />

                <div class="mt-2 max-h-48 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-md divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->availableTeams as $team)
                        <label class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <input
                                type="checkbox"
                                wire:click="toggleTeam({{ $team->id }})"
                                @checked(in_array($team->id, $selectedTeamIds, true))
                                class="rounded"
                            >
                            <flux:text size="sm">{{ $team->current_name }}</flux:text>
                        </label>
                    @empty
                        <div class="px-3 py-4">
                            <flux:text size="sm" variant="subtle">Команды не найдены.</flux:text>
                        </div>
                    @endforelse
                </div>

                @if (! empty($selectedTeamIds))
                    <flux:text size="sm" variant="subtle" class="mt-2">
                        Выбрано команд: {{ count($selectedTeamIds) }}
                    </flux:text>
                @endif
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
