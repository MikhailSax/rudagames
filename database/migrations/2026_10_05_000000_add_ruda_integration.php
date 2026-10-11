<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Связь игр CRM с мероприятиями Руды: eventDays[].id (event_day) и id серии (event_record)
        Schema::table('games', function (Blueprint $table) {
            $table->string('ruda_event_day_id', 36)->nullable()->unique()->after('id');
            $table->string('ruda_event_record_id', 36)->nullable()->index()->after('ruda_event_day_id');
        });

        // teams[].teamId из статистики Руды — после первого сопоставления по телефону
        // следующие игры команды находятся сразу по этому ID.
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('ruda_team_id')->nullable()->unique()->after('id');
        });

        // Заявки на игру из вебхука teamRegistered. Отдельно от game_participations:
        // зарегистрировавшаяся команда может не прийти, а участие — это факт игры.
        Schema::create('ruda_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('play_application_id')->unique();
            $table->string('ruda_event_day_id', 36)->index();
            $table->foreignId('game_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();

            $table->string('team_name')->nullable();
            $table->string('registrant_name')->nullable();
            $table->string('registrant_phone')->nullable();
            $table->string('registrant_email')->nullable();
            $table->string('captain_name')->nullable();
            $table->string('captain_phone')->nullable();
            $table->string('captain_email')->nullable();

            $table->timestamps();
        });

        // Журнал всех входящих вебхуков — для разбора, если что-то не сопоставилось
        Schema::create('ruda_webhook_calls', function (Blueprint $table) {
            $table->id();
            $table->string('event');
            $table->json('payload');
            $table->string('status'); // processed / failed
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruda_webhook_calls');
        Schema::dropIfExists('ruda_registrations');

        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['ruda_team_id']);
            $table->dropColumn('ruda_team_id');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropUnique(['ruda_event_day_id']);
            $table->dropIndex(['ruda_event_record_id']);
            $table->dropColumn(['ruda_event_day_id', 'ruda_event_record_id']);
        });
    }
};
