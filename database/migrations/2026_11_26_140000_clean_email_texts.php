<?php

use App\Services\Mail\MailText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Jednorázové vyčištění už načtených e-mailů a popisů zakázek od CSS a patiček */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('inbound_emails')->orderBy('id')->select(['id', 'body_text'])->chunkById(200, function ($rows) {
            foreach ($rows as $r) {
                $clean = MailText::clean((string) $r->body_text);
                if ($clean !== $r->body_text) {
                    DB::table('inbound_emails')->where('id', $r->id)->update(['body_text' => $clean]);
                }
            }
        });

        DB::table('tickets')->where('source', 'email')->orderBy('id')->select(['id', 'description'])->chunkById(200, function ($rows) {
            foreach ($rows as $r) {
                if ($r->description === null) {
                    continue;
                }
                $clean = MailText::forDescription($r->description);
                if ($clean !== $r->description) {
                    DB::table('tickets')->where('id', $r->id)->update(['description' => $clean]);
                }
            }
        });
    }

    public function down(): void
    {
        // nevratné (jen čištění textu)
    }
};
