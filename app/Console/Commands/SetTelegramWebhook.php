<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook';

    protected $description = 'Регистрирует URL вебхука в Telegram (запустить один раз после деплоя/смены домена)';

    public function handle(): int
    {
        $botToken = config('services.telegram.bot_token');

        if (! $botToken) {
            $this->error('TELEGRAM_BOT_TOKEN не задан в .env');

            return self::FAILURE;
        }

        $url = route('telegram.webhook');
        $secret = config('services.telegram.webhook_secret');

        if (! str_starts_with($url, 'https://')) {
            $this->error("URL вебхука должен быть https — сейчас: {$url}. Проверьте APP_URL в .env.");

            return self::FAILURE;
        }

        $payload = ['url' => $url];
        if ($secret) {
            $payload['secret_token'] = $secret;
        }

        $response = Http::post("https://api.telegram.org/bot{$botToken}/setWebhook", $payload);

        if (! $response->successful() || ! ($response->json('ok'))) {
            $this->error('Telegram отклонил запрос: '.$response->body());

            return self::FAILURE;
        }

        $this->info("Вебхук установлен: {$url}");

        return self::SUCCESS;
    }
}
