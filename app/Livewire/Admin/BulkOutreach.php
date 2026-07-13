<?php

namespace App\Livewire\Admin;

use App\Models\Communication;
use App\Models\Product;
use App\Models\Team;
use App\Services\Messenger\MessengerSenderInterface;
use App\Services\Sms\SmsSenderInterface;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class BulkOutreach extends Component
{
    public string $lifecycleFilter = '';
    public string $activityFilter = '';
    public ?int $favoriteProductFilter = null;

    public string $channel = 'sms';
    public string $messageText = '';
    public string $goalLabel = 'ручная рассылка';

    public bool $sent = false;
    public int $sentCount = 0;
    public int $skippedCount = 0;

    #[Computed]
    public function products()
    {
        return Product::orderBy('name')->get();
    }

    #[Computed]
    public function segmentTeams()
    {
        return $this->baseQuery()->get();
    }

    #[Computed]
    public function segmentCount(): int
    {
        return $this->baseQuery()->count();
    }

    private function baseQuery()
    {
        return Team::query()
            ->when($this->lifecycleFilter !== '', fn($q) => $q->where('lifecycle_stage', $this->lifecycleFilter))
            ->when($this->activityFilter !== '', fn($q) => $q->where('activity_status', $this->activityFilter))
            ->when($this->favoriteProductFilter, fn($q) => $q->where('favorite_product_id', $this->favoriteProductFilter));
    }

    public function send(SmsSenderInterface $sms, MessengerSenderInterface $messenger): void
    {
        $this->validate([
            'messageText' => 'required|string|min:3',
            'channel' => 'required|in:sms,email,messenger',
        ]);

        $teams = $this->baseQuery()->get();

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($teams as $team) {
            if ($this->channel === 'email' && !$team->email) {
                $skippedCount++;
                continue;
            }

            if ($this->channel === 'messenger' && !$team->phone) {
                $skippedCount++;
                continue;
            }

            $personalizedText = str_replace('{name}', $team->current_name, $this->messageText);

            $delivered = match ($this->channel) {
                'sms' => $sms->send($team->phone, $personalizedText),
                'messenger' => $messenger->send($team->phone, $personalizedText),
                default => $this->sendEmail($team->email, $personalizedText),
            };

            Communication::create([
                'team_id' => $team->id,
                'channel' => $this->channel,
                'goal' => $this->goalLabel,
                'status' => $delivered ? Communication::STATUS_SENT : Communication::STATUS_ERROR,
                'message_text' => $personalizedText,
                'sent_at' => now(),
                'approved_at' => now(),
            ]);

            $delivered ? $sentCount++ : $skippedCount++;
        }

        $this->sent = true;
        $this->sentCount = $sentCount;
        $this->skippedCount = $skippedCount;
    }

    private function sendEmail(string $email, string $message): bool
    {
        Mail::raw($message, function ($mail) use ($email) {
            $mail->to($email)->subject('Сообщение от Ruda Games');
        });

        return true;
    }

    public function resetOutreach(): void
    {
        $this->reset(['messageText', 'sent', 'sentCount', 'skippedCount']);
    }

    public function render()
    {
        return view('livewire.admin.bulk-outreach')
            ->layoutData(['title' => 'Массовая рассылка']);
    }
}
