<?php

namespace App\Services\Marketing;

use App\Models\Communication;
use App\Services\Messenger\MessengerSenderInterface;
use App\Services\Sms\SmsSenderInterface;
use Illuminate\Support\Facades\Mail;

class SendCommunicationService
{
    public function __construct(
        private readonly SmsSenderInterface $sms,
        private readonly MessengerSenderInterface $messenger,
    ) {
    }

    /**
     * Отправляет одобренный черновик выбранным каналом.
     * Вызывается из админ-панели после того, как руководитель выбрал канал
     * и (опционально) отредактировал текст.
     *
     * @param Communication $communication Черновик со статусом "черновик"
     * @param string $channel "sms", "email" или "messenger"
     * @param string $finalMessageText Финальный текст (после правок руководителя)
     */
    public function send(Communication $communication, string $channel, string $finalMessageText): bool
    {
        $team = $communication->team;
        $sent = match ($channel) {
            'sms' => $this->sms->send($team->phone, $finalMessageText),
            'messenger' => $this->messenger->send($team->phone, $finalMessageText),
            'email' => $this->sendEmail($team->email, $finalMessageText),
            default => throw new \InvalidArgumentException("Неизвестный канал: {$channel}"),
        };

        $communication->update([
            'channel' => $channel,
            'message_text' => $finalMessageText,
            'status' => $sent ? Communication::STATUS_SENT : Communication::STATUS_ERROR,
            'sent_at' => now(),
            'approved_at' => now(),
        ]);

        return $sent;
    }

    /**
     * Руководитель решил не связываться с этой командой — черновик отклоняется.
     */
    public function reject(Communication $communication): void
    {
        $communication->update([
            'status' => Communication::STATUS_REJECTED,
            'approved_at' => now(),
        ]);
    }

    private function sendEmail(?string $email, string $message): bool
    {
        if (!$email) {
            return false;
        }

        // TODO: заменить на реальный Mailable, когда определимся с шаблоном письма.
        // Пока — минимальная отправка через встроенный Mail фасад Laravel.
        Mail::raw($message, function ($mail) use ($email) {
            $mail->to($email)->subject('Сообщение от Ruda Games');
        });

        return true;
    }
}
