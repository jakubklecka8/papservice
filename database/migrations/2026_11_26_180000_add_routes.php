<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // výchozí bod trasy technika (domov / základna)
        Schema::table('users', function (Blueprint $table) {
            $table->string('home_address')->nullable()->after('phone');
            $table->decimal('home_lat', 10, 7)->nullable()->after('home_address');
            $table->decimal('home_lng', 10, 7)->nullable()->after('home_lat');
        });
        // dohledání souřadnic z adresy: kdy a s jakým výsledkem
        Schema::table('locations', function (Blueprint $table) {
            $table->timestamp('geocoded_at')->nullable();
            $table->string('geocode_status', 16)->nullable();   // ok|not_found|error|manual
        });
    }

    public function down(): void
    {
        Schema::table('locations', fn (Blueprint $t) => $t->dropColumn(['geocoded_at', 'geocode_status']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['home_address', 'home_lat', 'home_lng']));
    }
};
