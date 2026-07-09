<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->string('channel')->default('sms');
            $table->string('goal');
            $table->string('status');
            $table->text('message_text')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index('team_id');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
