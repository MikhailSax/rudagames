<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Разовые доходы вне регулярных игр из Руды (выездные игры и т.п.).
        // Категории руководитель заводит сам.
        Schema::create('income_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('received_on');
            $table->string('comment')->nullable();
            $table->timestamps();

            $table->index('received_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
        Schema::dropIfExists('income_categories');
    }
};
