<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Složka Odeslané – podle ní poznáme RITM, na které už se odpovědělo (např. ze staré aplikace) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->string('sent_folder')->nullable()->after('imap_folder');
        });
    }

    public function down(): void
    {
        Schema::table('mailboxes', fn (Blueprint $t) => $t->dropColumn('sent_folder'));
    }
};
