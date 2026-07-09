<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->string('team_name_at_time'); // название команды на момент ИМЕННО этой игры
            $table->unsignedTinyInteger('players_count');
            $table->decimal('revenue', 10, 2)->default(0);

            $table->timestamps();

            // одна команда не может дважды участвовать в одной и той же игре
            $table->unique(['game_id', 'team_id']);
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_participations');
    }
};
