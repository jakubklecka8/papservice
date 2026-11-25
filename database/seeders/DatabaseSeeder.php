<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ProjectSeeder::class, AdminSeeder::class]);

        if (! app()->isProduction() && config('pap.seed_demo')) {
            $this->call(DemoSeeder::class);
        }

        $this->call(WarehouseSeeder::class);
    }
}
