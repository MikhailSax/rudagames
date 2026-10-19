<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Доходы</flux:heading>
            <flux:text variant="subtle" class="mt-1">
                Две категории, внутри каждой свои подкатегории. Билеты по регулярным играм приходят из Руды сами, остальное вносится здесь.
            </flux:text>
        </div>
        <div class="flex items-end gap-2 shrink-0">
            <flux:input wire:model.live="month" type="month" label="Месяц" />
            <flux:button wire:click="createIncome" variant="primary" icon="plus">
                Добавить доход
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach ($this->groups as $group => $label)
            @php
                $groupCategories = $this->categories->get($group, collect());
                $isGameGroup = $group === \App\Models\IncomeCategory::GROUP_GAME;
                $groupTotal = $groupCategories->sum('month_total') + ($isGameGroup ? $this->ticketsTotal : 0);
            @endphp
            <flux:card wire:key="income-group-{{ $group }}" class="space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <flux:text size="sm" variant="subtle">{{ $label }}</flux:text>
                        <flux:heading size="lg">{{ number_format($groupTotal, 0, ',', ' ') }} ₽</flux:heading>
                    </div>
                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="createCategory('{{ $group }}')">
                        Подкатегория
                    </flux:button>
                </div>

                <div class="border-t border-zinc-100 dark:border-zinc-800 pt-2 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @if ($isGameGroup)
                        <div class="flex items-center justify-between gap-2 py-1.5">
                            <div class="flex items-center gap-2 min-w-0">
                                <flux:text class="truncate">Билеты</flux:text>
                                <flux:badge size="sm" color="zinc">из Руды</flux:badge>
                            </div>
                            <flux:text size="sm" variant="subtle">{{ number_format($this->ticketsTotal, 0, ',', ' ') }} ₽</flux:text>
                        </div>
                    @endif

                    @forelse ($groupCategories as $category)
                        <div class="flex items-center justify-between gap-2 py-1.5" wire:key="income-category-{{ $category->id }}">
                            <flux:text class="truncate">{{ $category->name }}</flux:text>
                            <div class="flex items-center gap-1 shrink-0">
                                <flux:text size="sm" variant="subtle">{{ number_format((float) $category->month_total, 0, ',', ' ') }} ₽</flux:text>
                                <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editCategory({{ $category->id }})" />
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="deleteCategory({{ $category->id }})"
                                    wire:confirm="Удалить подкатегорию «{{ $category->name }}»?"
                                />
                            </div>
                        </div>
                    @empty
                        @unless ($isGameGroup)
                            <flux:text size="sm" variant="subtle" class="py-1.5">Подкатегорий пока нет. Например: «Выездные мероприятия», «Спонсорство», «Гранты».</flux:text>
                        @endunless
                    @endforelse
                </div>
            </flux:card>
        @endforeach
    </div>

    @error('delete-category')
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    <flux:card class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <flux:heading size="md">
                Доходы за месяц: {{ number_format($this->monthTotal, 0, ',', ' ') }} ₽
            </flux:heading>
            <flux:select wire:model.live="groupFilter" class="sm:w-80">
                <flux:select.option value="">Все категории</flux:select.option>
                @foreach ($this->groups as $group => $label)
                    <flux:select.option value="{{ $group }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Дата</flux:table.column>
                <flux:table.column>Подкатегория</flux:table.column>
                <flux:table.column>Игра / место</flux:table.column>
                <flux:table.column>Контакт</flux:table.column>
                <flux:table.column>Комментарий</flux:table.column>
                <flux:table.column>Сумма</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->incomes as $income)
                    <flux:table.row wire:key="income-{{ $income->id }}">
                        <flux:table.cell>{{ $income->held_at?->format('d.m.Y H:i') ?? $income->received_on->format('d.m.Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium">{{ $income->category->name }}</div>
                            <flux:text size="sm" variant="subtle">{{ $income->category->groupLabel() }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($income->game)
                                <flux:link :href="route('admin.games.show', $income->game)" wire:navigate>{{ $income->game->name }}</flux:link>
                            @endif
                            @if ($income->venue)
                                <flux:text size="sm" variant="subtle">{{ $income->venue }}</flux:text>
                            @elseif (! $income->game)
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($income->contact_name || $income->contact_phone)
                                <div>{{ $income->contact_name }}</div>
                                <flux:text size="sm" variant="subtle">{{ $income->contact_phone }}</flux:text>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text size="sm" variant="subtle">{{ $income->comment ?? '' }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>{{ number_format($income->amount, 0, ',', ' ') }} ₽</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1 justify-end">
                                <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editIncome({{ $income->id }})" />
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="deleteIncome({{ $income->id }})"
                                    wire:confirm="Удалить доход {{ number_format($income->amount, 0, ',', ' ') }} ₽?"
                                />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            <flux:text variant="subtle">За этот месяц доходов, внесённых вручную, нет.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="income-category-form" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingCategoryId ? 'Изменить подкатегорию' : 'Новая подкатегория' }}</flux:heading>
                <flux:text size="sm" variant="subtle">{{ $this->groups[$categoryGroup] ?? '' }}</flux:text>
            </div>

            <flux:input wire:model="categoryName" label="Название" placeholder="Например, Выездные мероприятия" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Отмена</flux:button>
                </flux:modal.close>
                <flux:button wire:click="saveCategory" variant="primary">Сохранить</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="income-form" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingIncomeId ? 'Изменить доход' : 'Новый доход' }}</flux:heading>

            <flux:select wire:model.live="incomeGroup" label="Категория">
                @foreach ($this->groups as $group => $label)
                    <flux:select.option value="{{ $group }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($this->categoriesForIncomeGroup->isEmpty())
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.text>В этой категории ещё нет подкатегорий. Сначала добавьте подкатегорию.</flux:callout.text>
                </flux:callout>
            @else
                <flux:select wire:model="income_category_id" label="Подкатегория" placeholder="Выберите подкатегорию">
                    <flux:select.option value="">Выберите подкатегорию</flux:select.option>
                    @foreach ($this->categoriesForIncomeGroup as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @if ($incomeGroup === \App\Models\IncomeCategory::GROUP_GAME)
                <flux:select wire:model="game_id" label="Игра" placeholder="Выберите игру">
                    <flux:select.option value="">Выберите игру</flux:select.option>
                    @foreach ($this->gamesForSelect as $game)
                        <flux:select.option value="{{ $game->id }}">
                            {{ $game->played_at->format('d.m.Y H:i') }} · {{ $game->name }}{{ $game->venue ? ' · '.$game->venue : '' }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:text size="sm" variant="subtle">Билеты вносить не нужно, они приходят из Руды. Здесь только доходы сверх билетов.</flux:text>
            @else
                <flux:input wire:model="contact_name" label="Имя" placeholder="Кто заказал" />
                <flux:input wire:model="contact_phone" label="Телефон" placeholder="+7..." />
                <flux:input wire:model="venue" label="Место проведения" />
                <flux:input wire:model="held_at" type="datetime-local" label="Дата и время проведения" description="Можно указать прошедшую дату" />
            @endif

            <flux:input wire:model="amount" type="number" step="0.01" label="Сумма, ₽" />
            <flux:input wire:model="comment" label="Комментарий" placeholder="Необязательно" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Отмена</flux:button>
                </flux:modal.close>
                <flux:button wire:click="saveIncome" variant="primary">Сохранить</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
