<div class="flex flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Продукты и категории</flux:heading>
            <flux:text variant="subtle" class="mt-1">
                Форматы игр, которые вы проводите, и их разновидности внутри каждого формата.
            </flux:text>
        </div>
        <flux:button wire:click="createProduct" variant="primary" icon="plus">
            Добавить продукт
        </flux:button>
    </div>

    @if ($this->products->isNotEmpty())
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <flux:card class="space-y-1">
                <flux:text size="sm" variant="subtle">Продуктов</flux:text>
                <flux:heading size="lg">{{ $this->products->count() }}</flux:heading>
            </flux:card>
            <flux:card class="space-y-1">
                <flux:text size="sm" variant="subtle">Категорий</flux:text>
                <flux:heading size="lg">{{ $this->products->sum(fn ($p) => $p->categories->count()) }}</flux:heading>
            </flux:card>
            <flux:card class="space-y-1 col-span-2 sm:col-span-1">
                <flux:text size="sm" variant="subtle">Игр всего</flux:text>
                <flux:heading size="lg">{{ $this->products->sum('games_count') }}</flux:heading>
            </flux:card>
        </div>
    @endif

    <div class="flex flex-col gap-4">
        @forelse ($this->products as $product)
            @php $accent = $this->productAccent($product->id); @endphp
            <flux:card wire:key="product-{{ $product->id }}" class="space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full shrink-0 font-semibold {{ $accent['bg'] }} {{ $accent['text'] }}">
                            {{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <flux:heading size="md" class="truncate">{{ $product->name }}</flux:heading>
                            <flux:text size="sm" variant="subtle">
                                {{ $product->categories->count() }} {{ $this->pluralizeCategories($product->categories->count()) }}
                                · {{ $product->games_count }} игр
                            </flux:text>
                        </div>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <flux:button size="sm" variant="ghost" icon="plus" wire:click="createCategory({{ $product->id }})">
                            Категория
                        </flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="editProduct({{ $product->id }})">
                            Изменить
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            wire:click="deleteProduct({{ $product->id }})"
                            wire:confirm="Удалить продукт «{{ $product->name }}»?{{ $product->categories->isNotEmpty() ? ' Вместе с ним будут удалены все его категории ('.$product->categories->count().').' : '' }}"
                        >
                            Удалить
                        </flux:button>
                    </div>
                </div>

                @error('delete-product-'.$product->id)
                    <flux:callout variant="danger" icon="exclamation-triangle" class="!mt-2">
                        <flux:callout.text>{{ $message }}</flux:callout.text>
                    </flux:callout>
                @enderror

                @if ($product->categories->isNotEmpty())
                    <div class="border-t border-zinc-100 dark:border-zinc-800 -mx-4 px-4 sm:-mx-6 sm:px-6 pt-3">
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($product->categories as $category)
                                <div class="flex items-center justify-between gap-3 py-2" wire:key="category-{{ $category->id }}">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <flux:icon name="tag" class="w-4 h-4 text-zinc-400 shrink-0" />
                                        <flux:text class="truncate">{{ $category->name }}</flux:text>
                                        <flux:badge color="zinc" size="sm">{{ $category->games_count }} игр</flux:badge>
                                    </div>
                                    <div class="flex gap-1 shrink-0">
                                        <flux:tooltip content="Изменить категорию">
                                            <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editCategory({{ $category->id }})" />
                                        </flux:tooltip>
                                        <flux:tooltip content="Удалить категорию">
                                            <flux:button
                                                size="xs"
                                                variant="ghost"
                                                icon="trash"
                                                wire:click="deleteCategory({{ $category->id }})"
                                                wire:confirm="Удалить категорию «{{ $category->name }}»?"
                                            />
                                        </flux:tooltip>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('delete-category-*')
                            <flux:text size="sm" class="text-red-600 block pt-2">{{ $message }}</flux:text>
                        @enderror
                    </div>
                @else
                    <div class="border-t border-zinc-100 dark:border-zinc-800 pt-3 flex items-center justify-between gap-3">
                        <flux:text size="sm" variant="subtle">Категорий пока нет.</flux:text>
                        <flux:button size="sm" variant="ghost" icon="plus" wire:click="createCategory({{ $product->id }})">
                            Добавить категорию
                        </flux:button>
                    </div>
                @endif
            </flux:card>
        @empty
            <flux:card class="py-14">
                <div class="flex flex-col items-center text-center gap-3">
                    <div class="flex items-center justify-center w-12 h-12 rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon name="squares-2x2" class="w-6 h-6 text-zinc-400" />
                    </div>
                    <flux:heading size="md">Продуктов пока нет</flux:heading>
                    <flux:text variant="subtle" class="max-w-sm">
                        Продукт — это формат игры, например «Мозгобойня» или «Квизмашина».
                        Внутри каждого продукта можно завести категории — конкретные разновидности игры.
                    </flux:text>
                    <flux:button wire:click="createProduct" variant="primary" icon="plus" class="mt-2">
                        Добавить первый продукт
                    </flux:button>
                </div>
            </flux:card>
        @endforelse
    </div>

    <flux:modal name="product-form" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">
                {{ $editingProductId ? 'Редактировать продукт' : 'Новый продукт' }}
            </flux:heading>

            <flux:input wire:model="productName" label="Название" placeholder="Например, Мозгобойня" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Отмена</flux:button>
                </flux:modal.close>
                <flux:button wire:click="saveProduct" variant="primary">Сохранить</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="category-form" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingCategoryId ? 'Редактировать категорию' : 'Новая категория' }}
                </flux:heading>
                @if ($this->categoryProductForModal)
                    <flux:text size="sm" variant="subtle" class="mt-1">
                        Продукт: {{ $this->categoryProductForModal->name }}
                    </flux:text>
                @endif
            </div>

            <flux:input wire:model="categoryName" label="Название" placeholder="Например, Классическая 26" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Отмена</flux:button>
                </flux:modal.close>
                <flux:button wire:click="saveCategory" variant="primary">Сохранить</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
