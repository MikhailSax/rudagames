<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_goals', function (Blueprint $table) {
            $table->id();
            $table->date('month')->unique(); // всегда первое число месяца
            $table->decimal('target_revenue', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_goals');
    }
};
