<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Категории расходов руководитель заводит сам внутри одной из трёх групп
        // (ExpenseCategory::GROUPS): постоянные по играм / постоянные общие / переменные.
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->timestamps();

            $table->unique(['group', 'name']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            // Только у расходов группы «постоянные, привязанные к играм»
            $table->foreignId('game_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('spent_on');
            $table->string('comment')->nullable();
            $table->timestamps();

            $table->index('spent_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
