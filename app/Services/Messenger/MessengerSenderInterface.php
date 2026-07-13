<?php

namespace App\Services\Messenger;

interface MessengerSenderInterface
{
    /**
     * Отправляет сообщение в мессенджер (WhatsApp/Telegram и т.п.).
     *
     * @return bool true, если сообщение принято провайдером к отправке
     */
    public function send(string $phone, string $message): bool;
}
