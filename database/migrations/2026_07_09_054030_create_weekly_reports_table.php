<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_reports', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');

            // Продажи
            $table->decimal('revenue', 12, 2)->default(0);
            $table->decimal('profit', 12, 2)->default(0);
            $table->decimal('avg_check', 10, 2)->nullable();
            $table->decimal('avg_team_size', 5, 2)->nullable();

            // Клиенты
            $table->unsignedInteger('new_teams_count')->default(0);
            $table->unsignedInteger('returning_teams_count')->default(0);
            $table->unsignedInteger('lost_teams_count')->default(0);
            $table->unsignedInteger('reactivated_teams_count')->default(0);

            // Маркетинг
            $table->unsignedInteger('sms_sent_count')->default(0);
            $table->unsignedInteger('sms_conversion_count')->default(0);

            // AI-выводы (Claude API)
            $table->text('ai_summary')->nullable();

            $table->timestamps();

            $table->unique(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_reports');
    }
};
