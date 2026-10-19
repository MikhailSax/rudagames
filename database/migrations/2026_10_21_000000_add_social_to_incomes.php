<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Соцсети заказчика (ссылка, ник — свободный текст)
        Schema::table('incomes', function (Blueprint $table) {
            $table->text('social')->nullable()->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropColumn('social');
        });
    }
};
