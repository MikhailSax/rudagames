<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Доходы, как и расходы, — два уровня: категория (IncomeCategory::GROUPS)
        // и подкатегории, которые руководитель заводит сам. Уже созданные
        // подкатегории — доходы вне регулярных игр.
        Schema::table('income_categories', function (Blueprint $table) {
            $table->string('group')->default('other')->after('id');
            $table->dropUnique(['name']);
            $table->unique(['group', 'name']);
        });

        // Доп. доходы по конкретной игре (кроме билетов — они приходят из Руды)
        Schema::table('incomes', function (Blueprint $table) {
            $table->foreignId('game_id')->nullable()->after('income_category_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('game_id');
        });

        Schema::table('income_categories', function (Blueprint $table) {
            $table->dropUnique(['group', 'name']);
            $table->dropColumn('group');
            $table->unique(['name']);
        });
    }
};
