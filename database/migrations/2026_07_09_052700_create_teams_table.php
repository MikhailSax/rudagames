<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();

            // Идентификация команды — телефон капитана уникален, название команды нет
            $table->string('phone')->unique(); // нормализовано: только цифры, напр. 79021699021
            $table->string('current_name');    // последнее известное название
            $table->string('captain_name')->nullable();
            $table->string('email')->nullable();

            // Модуль 3: агрегаты, пересчитываются ежедневной джобой
            $table->timestamp('first_game_at')->nullable();
            $table->timestamp('last_game_at')->nullable();
            $table->unsignedInteger('games_count')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('avg_team_size', 5, 2)->nullable();
            $table->unsignedInteger('avg_interval_days')->nullable();

            // Модуль 5: Marketing Profile, та же ежедневная джоба
            $table->string('lifecycle_stage')->nullable();   // новая/развивающаяся/постоянная/ядро
            $table->string('activity_status')->nullable();   // активная/остывающая/спящая/потерянная
            $table->decimal('ltv', 12, 2)->nullable();
            $table->foreignId('favorite_product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->timestamps();

            $table->index('activity_status');
            $table->index('lifecycle_stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
