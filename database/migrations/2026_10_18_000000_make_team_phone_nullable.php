<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Команды-новички из итогов Руды приходят без телефона (в statisticsSubmitted
        // его нет) — храним их по ruda_team_id, телефон дописывается позже из заявки
        // или вручную. Уникальность сохраняется: NULL-значения не конфликтуют.
        Schema::table('teams', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
        });
    }
};
