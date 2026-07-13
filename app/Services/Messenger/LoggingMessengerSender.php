<?php

namespace App\Services\Messenger;

use Illuminate\Support\Facades\Log;

/**
 * Временная заглушка. Не отправляет реальные сообщения в мессенджер — только логирует.
 * Когда подключим WhatsApp/Telegram-провайдера (например WhatsApp Business API или
 * Telegram Bot API) — заменить на реальную реализацию MessengerSenderInterface
 * и указать её в AppServiceProvider (bind).
 */
class LoggingMessengerSender implements MessengerSenderInterface
{
    public function send(string $phone, string $message): bool
    {
        Log::info('[Мессенджер заглушка] Отправка сообщения', [
            'phone' => $phone,
            'message' => $message,
        ]);

        return true;
    }
}
