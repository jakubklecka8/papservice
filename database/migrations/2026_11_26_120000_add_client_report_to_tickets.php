<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Report klientovi (odpověď Zásilkovně na RITM e-mail s fotkami, popisem a km) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('client_report_sent_at')->nullable()->after('closed_at');
            $table->foreignId('client_report_by')->nullable()->after('client_report_sent_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_report_by');
            $table->dropColumn('client_report_sent_at');
        });
    }
};
