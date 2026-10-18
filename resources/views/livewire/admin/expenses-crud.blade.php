<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Расходы</flux:heading>
            <flux:text variant="subtle" class="mt-1">
                Три группы расходов, внутри каждой — свои категории. Расходы по играм автоматически попадают в финансы игры.
            </flux:text>
        </div>
        <div class="flex items-end gap-2 shrink-0">
            <flux:input wire:model.live="month" type="month" label="Месяц" />
            <flux:button wire:click="createExpense" variant="primary" icon="plus">
                Добавить расход
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        @foreach ($this->groups as $group => $label)
            @php $groupCategories = $this->categories->get($group, collect()); @endphp
            <flux:card wire:key="group-{{ $group }}" class="space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <flux:text size="sm" variant="subtle">{{ $label }}</flux:text>
                        <flux:heading size="lg">{{ number_format($groupCategories->sum('month_total'), 0, ',', ' ') }} ₽</flux:heading>
                    </div>
                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="createCategory('{{ $group }}')">
                        Категория
                    </flux:button>
                </div>

                <div class="border-t border-zinc-100 dark:border-zinc-800 pt-2 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($groupCategories as $category)
                        <div class="flex items-center justify-between gap-2 py-1.5" wire:key="expense-category-{{ $category->id }}">
                            <flux:text class="truncate">{{ $category->name }}</flux:text>
                            <div class="flex items-center gap-1 shrink-0">
                                <flux:text size="sm" variant="subtle">{{ number_format((float) $category->month_total, 0, ',', ' ') }} ₽</flux:text>
                                <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editCategory({{ $category->id }})" />
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="deleteCategory({{ $category->id }})"
                                    wire:confirm="Удалить категорию «{{ $category->name }}»?"
                                />
                            </div>
                        </div>
                    @empty
                        <flux:text size="sm" variant="subtle" class="py-1.5">Категорий пока нет.</flux:text>
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
                Расходы за месяц: {{ number_format($this->monthTotal, 0, ',', ' ') }} ₽
            </flux:heading>
            <flux:select wire:model.live="groupFilter" class="sm:w-80">
                <flux:select.option value="">Все группы</flux:select.option>
                @foreach ($this->groups as $group => $label)
                    <flux:select.option value="{{ $group }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Дата</flux:table.column>
                <flux:table.column>Категория</flux:table.column>
                <flux:table.column>Игра</flux:table.column>
                <flux:table.column>Комментарий</flux:table.column>
                <flux:table.column>Сумма</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->expenses as $expense)
                    <flux:table.row wire:key="expense-{{ $expense->id }}">
                        <flux:table.cell>{{ $expense->spent_on->format('d.m.Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium">{{ $expense->category->name }}</div>
                            <flux:text size="sm" variant="subtle">{{ $expense->category->groupLabel() }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($expense->game)
                                <flux:link :href="route('admin.games.show', $expense->game)" wire:navigate>{{ $expense->game->name }}</flux:link>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text size="sm" variant="subtle">{{ $expense->comment ?? '' }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>{{ number_format($expense->amount, 0, ',', ' ') }} ₽</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1 justify-end">
                                <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editExpense({{ $expense->id }})" />
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="deleteExpense({{ $expense->id }})"
                                    wire:confirm="Удалить расход {{ number_format($expense->amount, 0, ',', ' ') }} ₽?"
                                />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <flux:text variant="subtle">За этот месяц расходов нет.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="category-form" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingCategoryId ? 'Изменить категорию' : 'Новая категория' }}</flux:heading>
                <flux:text size="sm" variant="subtle">{{ $this->groups[$categoryGroup] ?? '' }}</flux:text>
            </div>

            <flux:input wire:model="categoryName" label="Название" placeholder="Например, Аренда зала" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Отмена</flux:button>
                </flux:modal.close>
                <flux:button wire:click="saveCategory" variant="primary">Сохранить</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="expense-form" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingExpenseId ? 'Изменить расход' : 'Новый расход' }}</flux:heading>

            <flux:select wire:model.live="expenseGroup" label="Группа">
                @foreach ($this->groups as $group => $label)
                    <flux:select.option value="{{ $group }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($this->categoriesForExpenseGroup->isEmpty())
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.text>В этой группе ещё нет категорий. Сначала добавьте категорию на странице расходов.</flux:callout.text>
                </flux:callout>
            @else
                <flux:select wire:model="expense_category_id" label="Категория" placeholder="Выберите категорию">
                    <flux:select.option value="">Выберите категорию</flux:select.option>
                    @foreach ($this->categoriesForExpenseGroup as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @if ($expenseGroup === \App\Models\ExpenseCategory::GROUP_GAME)
                <flux:select wire:model="game_id" label="Игра" placeholder="Выберите игру">
                    <flux:select.option value="">Выберите игру</flux:select.option>
                    @foreach ($this->gamesForSelect as $game)
                        <flux:select.option value="{{ $game->id }}">
                            {{ $game->played_at->format('d.m.Y H:i') }} · {{ $game->name }}{{ $game->venue ? ' · '.$game->venue : '' }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <flux:input wire:model="spent_on" type="date" label="Дата" />
            @endif

            <flux:input wire:model="amount" type="number" step="0.01" label="Сумма, ₽" />
            <flux:input wire:model="comment" label="Комментарий" placeholder="Необязательно" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Отмена</flux:button>
                </flux:modal.close>
                <flux:button wire:click="saveExpense" variant="primary">Сохранить</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
