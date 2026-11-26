<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // vzhled protokolu: standard | m4b (PROTOKOL – Wykonanych prac)
            $table->string('protocol_template', 32)->default('standard')->after('countries');
        });
        Schema::table('visits', function (Blueprint $table) {
            $table->string('signer_name')->nullable()->after('instruction_ref');      // kdo za zákazníka podepsal
            $table->longText('signature_technician')->nullable()->after('signer_name'); // PNG data URL
            $table->longText('signature_customer')->nullable()->after('signature_technician');
            $table->timestamp('signed_at')->nullable()->after('signature_customer');
        });
        DB::table('projects')->where('code', 'M4B')->update(['protocol_template' => 'm4b']);
    }

    public function down(): void
    {
        Schema::table('visits', fn (Blueprint $t) => $t->dropColumn(['signer_name', 'signature_technician', 'signature_customer', 'signed_at']));
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn('protocol_template'));
    }
};
