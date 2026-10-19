<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Отметки по оплате (выездные мероприятия и т.п.)
        Schema::table('incomes', function (Blueprint $table) {
            $table->boolean('is_prepaid')->default(false)->after('held_at');   // предоплата внесена
            $table->boolean('is_completed')->default(false)->after('is_prepaid'); // проведено, оплачено
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropColumn(['is_prepaid', 'is_completed']);
        });
    }
};
