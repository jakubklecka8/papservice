<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Location;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Testovací účty a provozovny pro testovací subdoménu. V produkci se nespouští.
 * Všechny účty mají heslo "demo1234".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $zas = Project::where('code', 'ZAS')->firstOrFail();
        $ec = $zas->endCustomers()->first();

        $locations = collect([
            ['code' => 'Z-BOX-OS-001', 'name' => 'Z-BOX Ostrava Hlavní nádraží', 'street' => 'Nádražní 196', 'city' => 'Ostrava', 'zip' => '702 00'],
            ['code' => 'Z-BOX-OS-002', 'name' => 'Z-BOX Ostrava Poruba', 'street' => 'Hlavní třída 583', 'city' => 'Ostrava', 'zip' => '708 00'],
            ['code' => 'Z-BOX-PR-001', 'name' => 'Z-BOX Praha Smíchov', 'street' => 'Plzeňská 8', 'city' => 'Praha', 'zip' => '150 00'],
            ['code' => 'Z-BOX-KAT-001', 'name' => 'Z-BOX Katowice Centrum', 'street' => 'ul. Mariacka 1', 'city' => 'Katowice', 'zip' => '40-014', 'country' => 'PL'],
        ])->map(fn ($l) => Location::updateOrCreate(
            ['project_id' => $zas->id, 'code' => $l['code']],
            $l + ['end_customer_id' => $ec->id, 'country' => $l['country'] ?? 'CZ'],
        ));

        $make = function (string $email, string $name, array $roles, string $locale = 'cs') {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'demo1234', 'locale' => $locale]);
            $user->syncRoles($roles);

            return $user;
        };

        $dispecer = $make('dispecer@demo.test', 'Demo Dispečer', [Role::Dispecer], 'pl');
        $tech1 = $make('technik1@demo.test', 'Demo Technik 1', [Role::Technik]);
        $tech2 = $make('technik2@demo.test', 'Demo Technik 2', [Role::Technik], 'pl');
        $spolecnik = $make('spolecnik@demo.test', 'Demo Společník', [Role::Spolecnik]);
        $make('ekonom@demo.test', 'Demo Ekonom', [Role::Ekonom]);
        $zakaznik = $make('zakaznik@demo.test', 'Demo Zákazník', [Role::Zakaznik]);

        foreach ([$dispecer, $tech1, $tech2] as $u) {
            $u->projects()->syncWithoutDetaching([$zas->id]);
        }
        $zakaznik->locations()->syncWithoutDetaching($locations->take(2)->pluck('id'));
    }
}
