<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SLA v hodinách podle priority, např. {"urgent":4,"high":24,"normal":72,"low":168}
        Schema::table('projects', function (Blueprint $table) {
            $table->json('sla_hours')->nullable()->after('protocol_template');
        });

        // Zařízení na provozovně (automat, kiosek, Z-BOX…) – soupis sériových čísel a předání
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 100);                       // druh zařízení
            $table->string('model', 150)->nullable();
            $table->string('serial_number', 100)->nullable()->index();
            $table->string('status', 16)->default('active');   // active|removed
            $table->date('installed_at')->nullable();
            $table->date('handover_at')->nullable();           // předání zákazníkovi
            $table->string('handover_to')->nullable();
            $table->text('note')->nullable();
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
        });

        // Historie zařízení: instalace, předání, servis, výměna, demontáž, poznámka
        Schema::create('device_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('old_serial', 100)->nullable();
            $table->string('new_serial', 100)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();
        });

        // Kontrolní seznamy podle projektu a typu služby
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('service_type', 16)->nullable();
            $table->string('name');
            $table->json('items');                              // [{id, text, required}]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Harmonogram: pravidelná údržba/profilaxe – sám zakládá zakázky s termínem
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('checklist_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('service_type', 16)->default('profilaxe');
            $table->string('title');
            $table->unsignedSmallInteger('interval_months')->default(3);
            $table->date('next_due_on');
            $table->unsignedSmallInteger('lead_days')->default(14);   // kolik dní předem založit zakázku
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();
        });

        // Dostupnost techniků (dovolená, nemoc, školení)
        Schema::create('technician_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['date_from', 'date_to']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('checklist_template_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('planned_at')->nullable()->index();
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->json('checklist')->nullable();             // [{id, text, required, result: ok|nok|na, note}]
        });
    }

    public function down(): void
    {
        Schema::table('visits', fn (Blueprint $t) => $t->dropColumn('checklist'));
        Schema::table('tickets', function (Blueprint $t) {
            $t->dropConstrainedForeignId('device_id');
            $t->dropConstrainedForeignId('schedule_id');
            $t->dropConstrainedForeignId('checklist_template_id');
            $t->dropColumn('planned_at');
        });
        Schema::dropIfExists('technician_absences');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('checklist_templates');
        Schema::dropIfExists('device_events');
        Schema::dropIfExists('devices');
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn('sla_hours'));
    }
};
