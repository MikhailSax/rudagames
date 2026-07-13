<?php

namespace App\Livewire\Admin;

use App\Models\Communication;
use App\Services\Marketing\SendCommunicationService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Модуль 6: Marketing Engine ежедневно создаёт ЧЕРНОВИКИ предложений (кому, зачем связаться).
 * Здесь руководитель просматривает черновики, при необходимости правит текст,
 * выбирает канал и вручную подтверждает реальную отправку — либо отклоняет черновик.
 */
#[Layout('layouts.app')]
class CommunicationDrafts extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public ?int $processingId = null;
    public string $channel = 'sms';
    public string $messageText = '';

    #[Computed]
    public function drafts()
    {
        return Communication::drafts()
            ->with('team')
            ->orderBy('created_at')
            ->paginate(20);
    }

    #[Computed]
    public function draftsCount(): int
    {
        return Communication::drafts()->count();
    }

    #[Computed]
    public function processingDraft(): ?Communication
    {
        if (! $this->processingId) {
            return null;
        }

        return Communication::with('team')->find($this->processingId);
    }

    public function openDraft(int $id): void
    {
        $draft = Communication::with('team')->findOrFail($id);

        $this->processingId = $draft->id;
        $this->channel = 'sms';
        $this->messageText = $draft->suggested_message_text ?? '';

        $this->resetErrorBag();
        $this->modal('process-draft')->show();
    }

    public function send(SendCommunicationService $service): void
    {
        $this->validate([
            'messageText' => 'required|string|min:3',
            'channel' => 'required|in:sms,email,messenger',
        ]);

        $draft = Communication::findOrFail($this->processingId);
        $service->send($draft, $this->channel, $this->messageText);

        $this->modal('process-draft')->close();
        $this->resetForm();
    }

    public function reject(SendCommunicationService $service): void
    {
        $draft = Communication::findOrFail($this->processingId);
        $service->reject($draft);

        $this->modal('process-draft')->close();
        $this->resetForm();
    }

    public function closeModal(): void
    {
        $this->modal('process-draft')->close();
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->processingId = null;
        $this->channel = 'sms';
        $this->messageText = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.admin.communication-drafts')
            ->layoutData(['title' => 'Черновики рассылок']);
    }
}
