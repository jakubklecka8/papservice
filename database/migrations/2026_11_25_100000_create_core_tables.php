<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jádro PAP Service (M1/M2): projekty, koncoví zákazníci, provozovny,
 * role a přístupy uživatelů.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Projekty = klienti PAP Service (Zásilkovna, Allegro, M4B, ...)
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();           // ZAS, ALG, M4B
            $table->string('name');
            $table->json('service_types')->nullable();      // ["servis","profilaxe"]
            $table->json('countries')->nullable();          // ["CZ","PL"]
            $table->boolean('is_active')->default(true);
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
        });

        // Koncoví zákazníci v rámci projektu (u M4B: KFC/SBX/BK AmRest, BK REX, PLK REX)
        Schema::create('end_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->json('countries')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->unique(['project_id', 'code']);
        });

        // Provozovny (místa s automaty / zařízeními)
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('end_customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 64);                     // ID u klienta (např. Z-BOX ID)
            $table->string('name');
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('zip', 16)->nullable();
            $table->string('country', 2)->default('CZ');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->unique(['project_id', 'code']);
            $table->index(['project_id', 'city']);
        });

        // Role uživatele – kombinovatelné (technik může být i dispečer)
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);                     // admin|ekonom|spolecnik|dispecer|technik|zakaznik
            $table->timestamps();
            $table->unique(['user_id', 'role']);
        });

        // Přístup interních rolí (dispečer, technik, společník) k projektům
        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        // Přístup zákazníka k provozovnám
        Schema::create('location_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['location_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_user');
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('end_customers');
        Schema::dropIfExists('projects');
    }
};
