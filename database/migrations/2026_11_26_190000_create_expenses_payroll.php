<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // jak se technikovi platí: proplacení (zaměstnanec/dohoda) nebo měsíční faktura (OSVČ)
        Schema::table('users', function (Blueprint $table) {
            $table->string('settlement_mode', 16)->default('reimburse')->after('locale');  // reimburse|invoice
            $table->string('ico', 16)->nullable()->after('settlement_mode');
        });

        // sazby odměn techniků – nejkonkrétnější řádek vyhrává (technik > projekt > typ služby)
        Schema::create('pay_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('service_type', 16)->nullable();
            $table->string('currency', 3)->default('CZK');
            $table->decimal('per_visit', 10, 2)->default(0);
            $table->decimal('per_km', 8, 2)->default(0);     // náhrada za PHM / km
            $table->decimal('per_hour', 8, 2)->default(0);   // za hodinu na místě
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'project_id', 'service_type']);
        });

        // měsíční vyúčtování technika – snímek čísel po potvrzení ekonomem
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('month', 7);                      // 2026-11
            $table->string('currency', 3)->default('CZK');
            $table->decimal('total', 12, 2);
            $table->json('data');
            $table->string('status', 16)->default('confirmed');  // confirmed|paid
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'month', 'currency']);
        });

        // výdaje s dokladem: technik → dispečer zkontroluje → ekonom schválí → potvrdí úhradu
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 16);                  // fuel|parts|material|parking|toll|lodging|tools|other
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('CZK');
            $table->date('spent_on');
            $table->decimal('liters', 8, 2)->nullable();
            $table->string('paid_with', 16)->default('own'); // own = platil technik (proplatit) | company = firemní karta
            $table->text('description')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_mime')->nullable();
            $table->string('status', 16)->default('submitted');  // submitted|checked|approved|paid|returned|rejected
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->text('return_note')->nullable();
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('pay_rates');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['settlement_mode', 'ico']));
    }
};
