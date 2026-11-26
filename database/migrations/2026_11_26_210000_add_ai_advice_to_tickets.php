<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->json('ai_advice')->nullable();        // poslední doporučení AI k závadě
            $table->timestamp('ai_advice_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', fn (Blueprint $t) => $t->dropColumn(['ai_advice', 'ai_advice_at']));
    }
};
