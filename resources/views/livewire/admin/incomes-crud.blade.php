<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Доходы</flux:heading>
            <flux:text variant="subtle" class="mt-1">
                Разовые доходы вне регулярных игр, например выездные игры. Учитываются в выручке аналитики и в недельных отчётах.
            </flux:text>
        </div>
        <div class="flex items-end gap-2 shrink-0">
            <flux:input wire:model.live="month" type="month" label="Месяц" />
            <flux:button wire:click="createIncome" variant="primary" icon="plus">
                Добавить доход
            </flux:button>
        </div>
    </div>

    <flux:card class="space-y-3">
        <div class="flex items-start justify-between gap-2">
            <div>
                <flux:text size="sm" variant="subtle">Доходы за месяц</flux:text>
                <flux:heading size="lg">{{ number_format($this->monthTotal, 0, ',', ' ') }} ₽</flux:heading>
            </div>
            <flux:button size="sm" variant="ghost" icon="plus" wire:click="createCategory">
                Категория
            </flux:button>
        </div>

        <div class="border-t border-zinc-100 dark:border-zinc-800 pt-2 divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($this->categories as $category)
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
                            wire:confirm="Удалить категорию «{{ $category->name }}»?"
                        />
                    </div>
                </div>
            @empty
                <flux:text size="sm" variant="subtle" class="py-1.5">Категорий пока нет. Например: «Выездная игра», «Корпоратив».</flux:text>
            @endforelse
        </div>

        @error('delete-category')
            <flux:text size="sm" class="text-red-600 block">{{ $message }}</flux:text>
        @enderror
    </flux:card>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Дата</flux:table.column>
                <flux:table.column>Категория</flux:table.column>
                <flux:table.column>Комментарий</flux:table.column>
                <flux:table.column>Сумма</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->incomes as $income)
                    <flux:table.row wire:key="income-{{ $income->id }}">
                        <flux:table.cell>{{ $income->received_on->format('d.m.Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium">{{ $income->category->name }}</div>
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
                        <flux:table.cell colspan="5">
                            <flux:text variant="subtle">За этот месяц доходов нет.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="income-category-form" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingCategoryId ? 'Изменить категорию' : 'Новая категория доходов' }}</flux:heading>

            <flux:input wire:model="categoryName" label="Название" placeholder="Например, Выездная игра" />

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

            @if ($this->categories->isEmpty())
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.text>Сначала добавьте категорию доходов, например «Выездная игра».</flux:callout.text>
                </flux:callout>
            @else
                <flux:select wire:model="income_category_id" label="Категория" placeholder="Выберите категорию">
                    <flux:select.option value="">Выберите категорию</flux:select.option>
                    @foreach ($this->categories as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input wire:model="received_on" type="date" label="Дата" />
            <flux:input wire:model="amount" type="number" step="0.01" label="Сумма, ₽" />
            <flux:input wire:model="comment" label="Комментарий" placeholder="Например, корпоратив на 40 человек" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Отмена</flux:button>
                </flux:modal.close>
                <flux:button wire:click="saveIncome" variant="primary">Сохранить</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
