<div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Черновики рассылок</flux:heading>
        <flux:badge color="amber" size="lg">{{ $this->draftsCount }} ждут решения</flux:badge>
    </div>

    <flux:text variant="subtle">
        Каждый день Marketing Engine анализирует базу команд и предлагает, с кем и зачем связаться.
        Реальную отправку — канал и финальный текст — подтверждает руководитель здесь.
    </flux:text>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Команда</flux:table.column>
            <flux:table.column>Цель</flux:table.column>
            <flux:table.column>Создан</flux:table.column>
            <flux:table.column>Предложенный текст</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->drafts as $draft)
                <flux:table.row wire:key="draft-{{ $draft->id }}">
                    <flux:table.cell>
                        <div class="font-medium">{{ $draft->team->current_name ?? '— команда удалена —' }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="blue" size="sm">{{ $draft->goal }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:text size="sm" variant="subtle">{{ $draft->created_at->format('d.m.Y') }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:text size="sm" variant="subtle" class="line-clamp-1 max-w-xs">
                            {{ $draft->suggested_message_text }}
                        </flux:text>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end">
                            <flux:button size="sm" variant="primary" wire:click="openDraft({{ $draft->id }})">
                                Обработать
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text variant="subtle">Черновиков нет — все обработаны.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->drafts->links() }}

    <flux:modal name="process-draft" class="max-w-xl">
        @if ($this->processingDraft)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $this->processingDraft->team->current_name ?? '— команда удалена —' }}</flux:heading>
                    <flux:badge color="blue" size="sm" class="mt-1">{{ $this->processingDraft->goal }}</flux:badge>
                </div>

                <flux:callout variant="secondary" icon="light-bulb">
                    <flux:callout.heading>Предложенный текст</flux:callout.heading>
                    <flux:callout.text>{{ $this->processingDraft->suggested_message_text }}</flux:callout.text>
                </flux:callout>

                <flux:select wire:model="channel" label="Канал отправки">
                    <flux:select.option value="sms">SMS</flux:select.option>
                    <flux:select.option value="email">Email</flux:select.option>
                    <flux:select.option value="messenger">Мессенджер (WhatsApp/Telegram)</flux:select.option>
                </flux:select>

                <flux:textarea
                    wire:model="messageText"
                    label="Финальный текст сообщения"
                    rows="4"
                />
                @error('messageText') <flux:text size="sm" class="text-red-600">{{ $message }}</flux:text> @enderror

                <div class="flex justify-between gap-2">
                    <flux:button
                        variant="ghost"
                        wire:click="reject"
                        wire:confirm="Отклонить этот черновик? Он больше не будет предложен по этой цели, пока не появятся новые основания."
                    >
                        Отклонить
                    </flux:button>

                    <div class="flex gap-2">
                        <flux:modal.close>
                            <flux:button variant="filled" wire:click="closeModal">Закрыть</flux:button>
                        </flux:modal.close>
                        <flux:button
                            variant="primary"
                            wire:click="send"
                            wire:confirm="Отправить это сообщение команде «{{ $this->processingDraft->team->current_name ?? '' }}»?"
                        >
                            Отправить
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
