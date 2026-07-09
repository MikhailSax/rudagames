<?php

namespace App\Services\Sms;

interface SmsSenderInterface
{
    /**
     * Отправляет SMS-сообщение.
     *
     * @return bool true, если сообщение принято провайдером к отправке
     */
    public function send(string $phone, string $message): bool;
}
