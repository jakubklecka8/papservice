<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Požadavky/zakázky, historie stavů, návštěvy techniků, díly, přílohy, protokoly.
 *
 * Záznamy vznikající v telefonu (návštěvy, díly, přílohy) mají UUID generované
 * klientem – opakovaná synchronizace po výpadku spojení tak nevytvoří duplicitu (6.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('number', 32)->nullable()->unique();   // PAP-2026-000123, přidělí se po uložení
            $table->foreignId('project_id')->constrained();
            $table->foreignId('end_customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete(); // e-mail může přijít bez jasné provozovny
            $table->string('service_type', 16)->default('servis'); // servis|profilaxe|udrzba|instalace
            $table->string('priority', 8)->default('normal');      // low|normal|high|urgent
            $table->string('status', 16)->default('new');
            $table->string('source', 8)->default('manual');        // manual|portal|email|import
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_name')->nullable();
            $table->string('reporter_email')->nullable();
            $table->string('reporter_phone', 32)->nullable();
            $table->foreignId('assigned_technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('due_at')->nullable();               // SLA termín
            $table->timestamp('closed_at')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->string('legacy_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'status']);
            $table->index(['assigned_technician_id', 'status']);
        });

        Schema::create('ticket_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users');
            $table->string('status', 16)->default('draft');        // draft|submitted|returned|approved
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->unsignedInteger('minutes_on_site')->nullable();
            $table->decimal('km', 8, 1)->nullable();
            $table->text('work_description')->nullable();          // provedené práce
            $table->text('repair_description')->nullable();        // popis opravy / příčina
            $table->string('test_result', 8)->nullable();          // ok|failed|na
            $table->text('test_note')->nullable();
            $table->string('instruction_ref')->nullable();         // použitý návod (v M5 vazba na verzi)
            $table->timestamp('submitted_at')->nullable();
            $table->text('return_note')->nullable();               // důvod vrácení dispečerem
            $table->timestamp('client_updated_at')->nullable();    // čas úpravy v telefonu (offline)
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('visit_parts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('part_number')->nullable();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('serial_removed')->nullable();          // demontované sériové číslo
            $table->string('serial_installed')->nullable();        // namontované sériové číslo
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('attachable');                          // Ticket | Visit
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 16)->default('photo');          // photo|document|email
            $table->string('disk', 16)->default('local');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('customer_visible')->default(false);   // vybrané fotky pro zákazníka
            $table->string('caption')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->string('language', 2);                         // cs|pl
            $table->string('status', 16)->default('draft');        // draft|approved
            $table->json('data');                                  // snímek dat z návštěvy v okamžiku vytvoření
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocols');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('visit_parts');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('ticket_status_history');
        Schema::dropIfExists('tickets');
    }
};
