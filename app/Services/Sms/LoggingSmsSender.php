<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Временная заглушка. Не отправляет реальные SMS — только логирует.
 * Когда подключим SMS.ru/SMSC — заменить на реальную реализацию
 * SmsSenderInterface и указать её в AppServiceProvider (bind).
 */
class LoggingSmsSender implements SmsSenderInterface
{
    public function send(string $phone, string $message): bool
    {
        Log::info('[SMS заглушка] Отправка SMS', [
            'phone' => $phone,
            'message' => $message,
        ]);

        return true;
    }
}
