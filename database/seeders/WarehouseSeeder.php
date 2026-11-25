<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\EndCustomer;
use App\Models\Project;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/** Sklady podle plánu: Zásilkovna, Allegro, AmRest, BK REX, PLK REX + sklady techniků */
class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $zas = Project::where('code', 'ZAS')->first();
        $alg = Project::where('code', 'ALG')->first();
        $m4b = Project::where('code', 'M4B')->first();
        $ec = fn (string $code) => EndCustomer::where('project_id', $m4b?->id)->where('code', $code)->value('id');

        $rows = [
            ['code' => 'ZAS', 'name' => 'Sklad Zásilkovna', 'project_id' => $zas?->id],
            ['code' => 'ALG', 'name' => 'Sklad Allegro', 'project_id' => $alg?->id],
            ['code' => 'AMREST', 'name' => 'Sklad AmRest (M4B)', 'project_id' => $m4b?->id, 'end_customer_id' => $ec('AMREST')],
            ['code' => 'BKREX', 'name' => 'Sklad BK REX (M4B)', 'project_id' => $m4b?->id, 'end_customer_id' => $ec('BKREX')],
            ['code' => 'PLKREX', 'name' => 'Sklad PLK REX (M4B)', 'project_id' => $m4b?->id, 'end_customer_id' => $ec('PLKREX')],
        ];
        foreach ($rows as $row) {
            Warehouse::updateOrCreate(['code' => $row['code']], $row + ['type' => 'project']);
        }

        User::whereHas('roleRows', fn ($q) => $q->where('role', Role::Technik->value))->get()
            ->each(fn (User $u) => Warehouse::forTechnician($u));
    }
}
