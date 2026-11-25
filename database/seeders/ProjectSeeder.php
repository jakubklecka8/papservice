<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

/** Projekty a koncoví zákazníci podle mapy portálu */
class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $projects = [
            ['code' => 'ZAS', 'name' => 'Zásilkovna', 'service_types' => ['servis', 'profilaxe'], 'countries' => ['CZ', 'PL'],
                'end_customers' => [['code' => 'ZAS', 'name' => 'Zásilkovna', 'countries' => ['CZ', 'PL']]]],
            ['code' => 'ALG', 'name' => 'Allegro', 'service_types' => ['servis', 'udrzba'], 'countries' => ['PL', 'CZ'],
                'end_customers' => [['code' => 'ALG', 'name' => 'Allegro', 'countries' => ['PL', 'CZ']]]],
            ['code' => 'M4B', 'name' => 'M4B', 'service_types' => ['servis', 'instalace'], 'countries' => ['CZ', 'PL', 'DE'],
                'end_customers' => [
                    ['code' => 'AMREST', 'name' => 'KFC · SBX · BK AmRest', 'countries' => ['CZ', 'DE']],
                    ['code' => 'BKREX', 'name' => 'BK REX', 'countries' => ['CZ', 'PL']],
                    ['code' => 'PLKREX', 'name' => 'PLK REX', 'countries' => ['CZ', 'PL']],
                ]],
        ];

        foreach ($projects as $row) {
            $endCustomers = $row['end_customers'];
            unset($row['end_customers']);

            $project = Project::updateOrCreate(['code' => $row['code']], $row);
            foreach ($endCustomers as $ec) {
                $project->endCustomers()->updateOrCreate(['code' => $ec['code']], $ec);
            }
        }
    }
}
