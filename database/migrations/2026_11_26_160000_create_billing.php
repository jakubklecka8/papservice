<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ceník: cena za zakázku + sazba za km, podle projektu a typu služby
        Schema::create('tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('service_type', 16)->nullable();   // null = platí pro všechny typy
            $table->string('currency', 3)->default('CZK');
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('km_rate', 8, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'service_type']);
        });

        // Cena u zakázky – null = podle ceníku; price_total = ručně zadaná celková částka
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('price_currency', 3)->nullable();
            $table->decimal('price_base', 10, 2)->nullable();
            $table->decimal('price_km_rate', 8, 2)->nullable();
            $table->decimal('price_extra', 10, 2)->nullable();
            $table->decimal('price_total', 10, 2)->nullable();
            $table->string('price_note')->nullable();
            $table->timestamp('price_locked_at')->nullable();
        });

        $now = now();
        $defaults = [
            'ZAS' => ['service_type' => 'servis', 'currency' => 'CZK', 'base_price' => 450, 'km_rate' => 2.5, 'note' => null],
            'M4B' => ['service_type' => 'servis', 'currency' => 'EUR', 'base_price' => 120, 'km_rate' => 0, 'note' => 'Doprava v ceně'],
        ];
        foreach ($defaults as $code => $row) {
            $projectId = DB::table('projects')->where('code', $code)->value('id');
            if ($projectId) {
                DB::table('tariffs')->insert($row + ['project_id' => $projectId, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tickets', fn (Blueprint $t) => $t->dropColumn(['price_currency', 'price_base', 'price_km_rate', 'price_extra', 'price_total', 'price_note', 'price_locked_at']));
        Schema::dropIfExists('tariffs');
    }
};
