<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Принимает апдейты от Telegram-бота (webhook). Единственная задача на сейчас —
 * связать telegram_chat_id с командой, когда она переходит по своей персональной
 * ссылке (Team::telegramInviteUrl()) и нажимает Start.
 */
class TelegramWebhookController
{
    public function __invoke(Request $request): Response
    {
        $expectedSecret = config('services.telegram.webhook_secret');

        if ($expectedSecret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $expectedSecret) {
            abort(403);
        }

        $message = $request->input('message');
        $chatId = $message['chat']['id'] ?? null;
        $text = trim($message['text'] ?? '');

        if ($chatId && str_starts_with($text, '/start')) {
            $this->handleStart((string) $chatId, $text);
        }

        return response()->noContent();
    }

    private function handleStart(string $chatId, string $text): void
    {
        [, $token] = array_pad(explode(' ', $text, 2), 2, null);

        if (! $token) {
            $this->reply($chatId, 'Привет! Чтобы подключить уведомления от Ruda Games, перейдите по персональной ссылке из личного кабинета — обычная кнопка Start её не заменит.');

            return;
        }

        $team = Team::where('telegram_link_token', $token)->first();

        if (! $team) {
            $this->reply($chatId, 'Эта ссылка недействительна или устарела. Попросите новую в Ruda Games.');

            return;
        }

        $team->update([
            'telegram_chat_id' => $chatId,
            'telegram_linked_at' => now(),
        ]);

        $this->reply($chatId, "Готово! Команда «{$team->current_name}» подключена — теперь напоминания и новости будем присылать сюда.");
    }

    private function reply(string $chatId, string $text): void
    {
        $botToken = config('services.telegram.bot_token');

        if (! $botToken) {
            return;
        }

        $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        if (! $response->successful()) {
            Log::warning('[Telegram webhook] Не удалось ответить пользователю', [
                'chat_id' => $chatId,
                'status' => $response->status(),
            ]);
        }
    }
}
