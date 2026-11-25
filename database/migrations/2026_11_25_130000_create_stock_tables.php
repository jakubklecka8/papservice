<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sklady (M4, krok 08).
 * Stav zásob se nikde neukládá jako číslo – počítá se vždy ze součtu pohybů,
 * takže převod i spotřeba na zakázce ho změní správně a dá se dohledat proč (4.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('type', 16);                       // central | project | technician
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('end_customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete(); // sklad (auto) technika
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Katalog dílů
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('part_number', 64)->unique();
            $table->string('name');
            $table->string('unit', 8)->default('ks');
            $table->boolean('serialized')->default(false);   // vyžaduje sériová čísla
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 16);                       // receipt | transfer | consumption | adjustment
            $table->foreignId('part_id')->constrained();
            $table->decimal('quantity', 10, 2);               // vždy kladné
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses');
            $table->string('serial_number')->nullable();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->cascadeOnDelete(); // spotřeba z návštěvy
            $table->foreignId('visit_part_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['part_id', 'from_warehouse_id']);
            $table->index(['part_id', 'to_warehouse_id']);
        });

        Schema::create('stock_minimums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_qty', 10, 2);
            $table->timestamps();
            $table->unique(['warehouse_id', 'part_id']);
        });

        Schema::table('visit_parts', function (Blueprint $table) {
            $table->foreignId('part_id')->nullable()->after('visit_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visit_parts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('part_id');
        });
        Schema::dropIfExists('stock_minimums');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('parts');
        Schema::dropIfExists('warehouses');
    }
};
