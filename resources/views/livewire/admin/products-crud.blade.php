<div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Продукты и категории</flux:heading>
        <flux:button wire:click="createProduct" variant="primary" icon="plus">
            Добавить продукт
        </flux:button>
    </div>

    <div class="space-y-4">
        @forelse ($this->products as $product)
            <flux:card wire:key="product-{{ $product->id }}" class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <flux:heading size="md">{{ $product->name }}</flux:heading>
                        <flux:badge color="zinc" size="sm">{{ $product->games_count }} игр</flux:badge>
                    </div>
                    <div class="flex gap-2">
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
                            wire:confirm="Удалить продукт «{{ $product->name }}»?"
                        >
                            Удалить
                        </flux:button>
                    </div>
                </div>

                @error('delete-product-'.$product->id)
                <flux:text size="sm" class="text-red-600">{{ $message }}</flux:text>
                @enderror

                @if ($product->categories->isNotEmpty())
                    <div class="flex flex-wrap gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                        @foreach ($product->categories as $category)
                            <div class="flex items-center gap-1" wire:key="category-{{ $category->id }}">
                                <flux:badge color="blue" size="sm">
                                    {{ $category->name }} ({{ $category->games_count }})
                                </flux:badge>
                                <button
                                    type="button"
                                    wire:click="editCategory({{ $category->id }})"
                                    class="text-xs text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 underline"
                                >
                                    изм.
                                </button>
                                <button
                                    type="button"
                                    wire:click="deleteCategory({{ $category->id }})"
                                    wire:confirm="Удалить категорию «{{ $category->name }}»?"
                                    class="text-xs text-zinc-400 hover:text-red-600 underline"
                                >
                                    удал.
                                </button>
                            </div>
                        @endforeach
                    </div>
                    @error('delete-category-*')
                    <flux:text size="sm" class="text-red-600">{{ $message }}</flux:text>
                    @enderror
                @else
                    <flux:text size="sm" variant="subtle">Категорий пока нет.</flux:text>
                @endif
            </flux:card>
        @empty
            <flux:card>
                <flux:text variant="subtle" class="text-center block py-8">Продуктов пока нет.</flux:text>
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
            <flux:heading size="lg">
                {{ $editingCategoryId ? 'Редактировать категорию' : 'Новая категория' }}
            </flux:heading>

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
