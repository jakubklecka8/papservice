<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Příjem požadavků e-mailem (M3, krok 07).
 * Hesla schránek a API klíč se ukládají šifrovaně (APP_KEY).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();          // šifrované tam, kde jde o tajemství
            $table->timestamps();
        });

        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('project_id')->constrained();
            $table->string('imap_host');
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption', 8)->default('ssl');   // ssl | tls | none
            $table->string('imap_username');
            $table->text('imap_password');                          // šifrované
            $table->string('imap_folder')->default('INBOX');
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_encryption', 8)->nullable();       // ssl | tls | none
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();              // šifrované
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->boolean('dedicated')->default(true);            // chodí sem jen požadavky?
            $table->boolean('auto_reply')->default(true);           // posílat potvrzení s číslem
            $table->boolean('is_active')->default(true);
            $table->timestamp('import_since')->nullable();          // starší zprávy se nečtou
            $table->unsignedBigInteger('last_uid')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('inbound_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->string('message_id', 512);
            $table->char('message_hash', 64);                      // sha256(message_id) – kvůli délce indexu
            $table->string('in_reply_to', 512)->nullable();
            $table->text('references')->nullable();
            $table->unsignedBigInteger('uid')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('subject', 1000)->nullable();
            $table->longText('body_text')->nullable();
            $table->json('attachments')->nullable();               // [{path, name, mime, size}]
            $table->timestamp('received_at')->nullable();
            // new | ticket | appended | review | ignored | error
            $table->string('status', 16)->default('new');
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->json('ai_result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->string('confirmation_message_id', 512)->nullable();
            $table->timestamps();
            $table->unique(['mailbox_id', 'message_hash']);
            $table->index('status');
        });

        // Všechna Message-ID patřící k zakázce (příchozí i naše potvrzení) – podle nich se páruje vlákno
        Schema::create('ticket_message_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('message_id', 512);
            $table->char('message_hash', 64)->unique();
            $table->timestamps();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('needs_review')->default(false)->after('source');   // nejasná provozovna z e-mailu
        });
    }

    public function down(): void
    {
        Schema::table('tickets', fn (Blueprint $t) => $t->dropColumn('needs_review'));
        Schema::dropIfExists('ticket_message_ids');
        Schema::dropIfExists('inbound_emails');
        Schema::dropIfExists('mailboxes');
        Schema::dropIfExists('settings');
    }
};
