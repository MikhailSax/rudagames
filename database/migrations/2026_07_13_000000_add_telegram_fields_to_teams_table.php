<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Telegram Bot API не умеет писать первым на номер телефона — только тем,
            // кто сам нажал Start у бота. telegram_chat_id заполняется вебхуком, когда
            // команда переходит по своей персональной ссылке (telegram_link_token).
            $table->string('telegram_chat_id')->nullable()->after('email');
            $table->string('telegram_link_token', 64)->nullable()->unique()->after('telegram_chat_id');
            $table->timestamp('telegram_linked_at')->nullable()->after('telegram_link_token');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_link_token', 'telegram_linked_at']);
        });
    }
};
