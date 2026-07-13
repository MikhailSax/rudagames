<?php

namespace App\Services\Messenger;

use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Отправляет сообщения через Telegram Bot API.
 *
 * Важное ограничение Telegram: бот не может написать первым произвольному номеру
 * телефона — только тому, кто сам нажал Start у бота. Поэтому отправка возможна
 * только командам с уже привязанным telegram_chat_id (см. Team::telegramInviteUrl()
 * и TelegramWebhookController, который и заполняет эту привязку).
 */
class TelegramMessengerSender implements MessengerSenderInterface
{
    public function __construct(private readonly ?string $botToken) {}

    public function send(string $phone, string $message): bool
    {
        if (! $this->botToken) {
            Log::warning('[Telegram] Отправка невозможна: TELEGRAM_BOT_TOKEN не настроен.');

            return false;
        }

        $chatId = Team::where('phone', $phone)->value('telegram_chat_id');

        if (! $chatId) {
            Log::info('[Telegram] Команда ещё не подключила Telegram — сообщение не отправлено.', [
                'phone' => $phone,
            ]);

            return false;
        }

        $response = Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
        ]);

        if (! $response->successful()) {
            Log::warning('[Telegram] Ошибка отправки сообщения', [
                'phone' => $phone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response->successful();
    }
}
