<div class="flex flex-col gap-6">
    <flux:heading size="xl">Массовая рассылка</flux:heading>

@if ($sent)
    <flux:callout variant="success" icon="check-circle">
        <flux:callout.heading>Рассылка завершена</flux:callout.heading>
        <flux:callout.text>
            Отправлено: {{ $sentCount }}. Пропущено (нет email/телефона или ошибка): {{ $skippedCount }}.
        </flux:callout.text>
        <x-slot name="actions">
            <flux:button wire:click="resetOutreach">Новая рассылка</flux:button>
        </x-slot>
    </flux:callout>
@else
    <flux:card class="space-y-4">
        <flux:heading size="md">1. Выберите сегмент</flux:heading>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <flux:select wire:model.live="lifecycleFilter" label="Стадия жизненного цикла" placeholder="Все стадии">
                <flux:select.option value="">Все стадии</flux:select.option>
                <flux:select.option value="новая">Новая</flux:select.option>
                <flux:select.option value="развивающаяся">Развивающаяся</flux:select.option>
                <flux:select.option value="постоянная">Постоянная</flux:select.option>
                <flux:select.option value="ядро сообщества">Ядро сообщества</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="activityFilter" label="Статус активности" placeholder="Все статусы">
                <flux:select.option value="">Все статусы</flux:select.option>
                <flux:select.option value="активная">Активная</flux:select.option>
                <flux:select.option value="остывающая">Остывающая</flux:select.option>
                <flux:select.option value="спящая">Спящая</flux:select.option>
                <flux:select.option value="потерянная">Потерянная</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="favoriteProductFilter" label="Любимый продукт" placeholder="Любой">
                <flux:select.option value="">Любой</flux:select.option>
                @foreach ($this->products as $product)
                    <flux:select.option value="{{ $product->id }}">{{ $product->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:callout variant="secondary" icon="users">
            <flux:callout.text>
                В сегмент попадает <strong>{{ $this->segmentCount }}</strong> команд{{ $this->segmentCount == 1 ? 'а' : ($this->segmentCount >= 2 && $this->segmentCount <= 4 ? 'ы' : '') }}.
            </flux:callout.text>
        </flux:callout>
    </flux:card>

    <flux:card class="space-y-4">
        <flux:heading size="md">2. Напишите сообщение</flux:heading>

        <flux:select wire:model="channel" label="Канал отправки">
            <flux:select.option value="sms">SMS</flux:select.option>
            <flux:select.option value="email">Email (команды без email будут пропущены)</flux:select.option>
            <flux:select.option value="messenger">Telegram (команды без привязанного Telegram будут пропущены)</flux:select.option>
        </flux:select>
        @if ($channel === 'messenger')
            <flux:callout variant="secondary" icon="paper-airplane">
                <flux:callout.text>
                    Из {{ $this->segmentCount }} команд в сегменте Telegram подключён у {{ $this->segmentTelegramLinkedCount }}.
                    Остальным нужно сначала перейти по персональной ссылке (страница команды → «Подключить Telegram»).
                </flux:callout.text>
            </flux:callout>
        @endif

        <flux:textarea
            wire:model="messageText"
            label="Текст сообщения"
            placeholder="Привет, {name}! ..."
            rows="4"
        />
        <flux:text size="sm" variant="subtle">
            Используйте {name} — оно автоматически заменится на название команды.
        </flux:text>
        @error('messageText') <flux:text size="sm" class="text-red-600">{{ $message }}</flux:text> @enderror

        <div class="flex justify-end">
            <flux:button
                variant="primary"
                wire:click="send"
                wire:confirm="Отправить это сообщение {{ $this->segmentCount }} командам? Действие необратимо."
            >
                Отправить {{ $this->segmentCount }} команд{{ $this->segmentCount == 1 ? 'е' : 'ам' }}
            </flux:button>
        </div>
    </flux:card>

    @if ($this->segmentCount > 0 && $this->segmentCount <= 20)
        <flux:card>
            <flux:heading size="md" class="mb-3">Кому будет отправлено</flux:heading>
            <div class="flex flex-wrap gap-2">
                @foreach ($this->segmentTeams as $team)
                    <flux:badge color="zinc" size="sm">{{ $team->current_name }}</flux:badge>
                @endforeach
            </div>
        </flux:card>
        @endif
        @endif
        </div>
