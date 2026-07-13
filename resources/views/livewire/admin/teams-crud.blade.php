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
            <flux:table.column>Последняя игра</flux:table.column>
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
                    <flux:table.cell>
                        <div class="flex items-center gap-2" x-data="{ copied: false }">
                            <span>{{ $team->phone }}</span>
                            <button
                                type="button"
                                title="Скопировать номер"
                                x-on:click="navigator.clipboard.writeText('{{ $team->phone }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                            >
                                <flux:icon x-show="!copied" name="clipboard" class="w-4 h-4" />
                                <flux:icon x-show="copied" x-cloak name="check" class="w-4 h-4 text-green-600" />
                            </button>
                            @if ($team->isTelegramLinked())
                                <flux:icon name="paper-airplane" class="w-3.5 h-3.5 text-blue-500" title="Telegram подключён" />
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $team->games_count }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $team->last_game_at ? $team->last_game_at->format('d.m.Y') : '—' }}
                    </flux:table.cell>
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
                            <flux:button size="sm" variant="ghost" wire:click="openTelegram({{ $team->id }})">
                                Telegram
                            </flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="viewHistory({{ $team->id }})">
                                История
                            </flux:button>
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
                    <flux:table.cell colspan="8">
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

    <flux:modal name="team-history" class="max-w-2xl">
        <div class="space-y-4">
            <flux:heading size="lg">История коммуникаций</flux:heading>

            <div class="space-y-3 max-h-96 overflow-y-auto">
                @forelse ($this->historyForOpenTeam as $comm)
                    <flux:card class="space-y-1">
                        <div class="flex items-center justify-between">
                            <flux:text size="sm" variant="subtle">
                                {{ $comm->sent_at->format('d.m.Y H:i') }} · {{ strtoupper($comm->channel ?? '—') }}
                            </flux:text>
                            <flux:badge
                                size="sm"
                                color="{{ $comm->status === 'отправлено' ? 'green' : ($comm->status === 'ошибка' ? 'red' : 'zinc') }}"
                            >
                                {{ $comm->status }}
                            </flux:badge>
                        </div>
                        <flux:badge color="blue" size="sm">{{ $comm->goal }}</flux:badge>
                        <flux:text size="sm">{{ $comm->message_text }}</flux:text>
                    </flux:card>
                @empty
                    <flux:text variant="subtle">По этой команде пока не было коммуникаций.</flux:text>
                @endforelse
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="filled">Закрыть</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="team-telegram" class="max-w-md">
        @if ($this->telegramTeamForModal)
            <div class="space-y-6">
                <flux:heading size="lg">Telegram — {{ $this->telegramTeamForModal->current_name }}</flux:heading>

                @if ($this->telegramTeamForModal->isTelegramLinked())
                    <flux:callout variant="success" icon="check-circle">
                        <flux:callout.heading>Подключён</flux:callout.heading>
                        <flux:callout.text>
                            С {{ $this->telegramTeamForModal->telegram_linked_at?->format('d.m.Y') }}.
                            Команде можно писать в разделах «Черновики рассылок» и «Массовая рассылка».
                        </flux:callout.text>
                    </flux:callout>

                    <div class="flex justify-end">
                        <flux:button
                            variant="ghost"
                            wire:click="unlinkTelegram"
                            wire:confirm="Отвязать Telegram у этой команды? Чтобы снова получать сообщения, им нужно будет заново перейти по ссылке."
                        >
                            Отвязать
                        </flux:button>
                    </div>
                @elseif ($this->telegramTeamForModal->telegramInviteUrl())
                    <flux:text variant="subtle">
                        Отправьте эту ссылку капитану команды (например, вместе с SMS). Как только он перейдёт
                        по ней и нажмёт Start в Telegram, команда появится доступной для рассылок.
                    </flux:text>

                    <div class="flex items-center gap-2" x-data="{ copied: false }">
                        <flux:input readonly value="{{ $this->telegramTeamForModal->telegramInviteUrl() }}" class="flex-1" />
                        <flux:button
                            variant="ghost"
                            icon="clipboard"
                            x-on:click="navigator.clipboard.writeText('{{ $this->telegramTeamForModal->telegramInviteUrl() }}'); copied = true; setTimeout(() => copied = false, 1500)"
                        >
                            <span x-show="!copied">Копировать</span>
                            <span x-show="copied" x-cloak>Скопировано</span>
                        </flux:button>
                    </div>
                @else
                    <flux:callout variant="warning" icon="exclamation-triangle">
                        <flux:callout.text>
                            Telegram-бот ещё не настроен (нет TELEGRAM_BOT_USERNAME в конфигурации).
                            Обратитесь к разработчику.
                        </flux:callout.text>
                    </flux:callout>
                @endif

                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="filled">Закрыть</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
