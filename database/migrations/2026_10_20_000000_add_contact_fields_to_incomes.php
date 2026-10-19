<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Кто заказал и где/когда проводили (выездные мероприятия и т.п.).
        // В БД поля необязательные: у доходов по игре место и время берутся из игры,
        // у старых записей их нет. Обязательность проверяется в форме.
        Schema::table('incomes', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('game_id');
            $table->string('contact_phone')->nullable()->after('contact_name');
            $table->string('venue')->nullable()->after('contact_phone');
            $table->timestamp('held_at')->nullable()->after('venue');
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'contact_phone', 'venue', 'held_at']);
        });
    }
};
