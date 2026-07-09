<?php


// ВАЖНО: ->change() требует пакет doctrine/dbal.
// Если не установлен: composer require doctrine/dbal

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            // Черновик создаётся Marketing Engine без канала — руководитель выбирает
            // SMS или email вручную в админке перед отправкой.
            $table->string('channel')->nullable()->default(null)->change();

            // Кто и когда отредактировал/утвердил черновик перед отправкой (для истории).
            $table->text('suggested_message_text')->nullable()->after('message_text');
            $table->timestamp('approved_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->string('channel')->default('sms')->change();
            $table->dropColumn(['suggested_message_text', 'approved_at']);
        });
    }
};
