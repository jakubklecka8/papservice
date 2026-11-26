<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Externí identifikátory od klientů:
 *  - tickets.external_ref – číslo požadavku u klienta (Zásilkovna RITM…)
 *  - locations.external_id – ID zařízení/boxu u klienta (Locker ID)
 *  - mailboxes.parser – jak číst e-maily (ai | zasilkovna_ritm)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('external_ref', 64)->nullable()->after('number');
            $table->unique(['project_id', 'external_ref']);
        });
        Schema::table('locations', function (Blueprint $table) {
            $table->string('external_id', 64)->nullable()->after('code');
            $table->index(['project_id', 'external_id']);
        });
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->string('parser', 24)->default('ai')->after('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('mailboxes', fn (Blueprint $t) => $t->dropColumn('parser'));
        Schema::table('locations', function (Blueprint $t) {
            $t->dropIndex(['project_id', 'external_id']);
            $t->dropColumn('external_id');
        });
        Schema::table('tickets', function (Blueprint $t) {
            $t->dropUnique(['project_id', 'external_ref']);
            $t->dropColumn('external_ref');
        });
    }
};
