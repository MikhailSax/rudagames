<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('game_categories')->cascadeOnDelete();

            $table->string('name');          // "Классическая #100 (+- / Ставки)" — значение из "Пакет"
            $table->timestamp('played_at');  // дата + время игры
            $table->string('venue')->nullable();
            $table->decimal('cost', 10, 2)->nullable();

            // Модуль 2: финансы, вносятся вручную ПОСЛЕ игры
            $table->decimal('actual_revenue', 12, 2)->nullable();
            $table->decimal('actual_expenses', 12, 2)->nullable();

            $table->timestamps();

            $table->index('played_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
