<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Admin účet z .env (PAP_ADMIN_EMAIL / PAP_ADMIN_PASSWORD). Heslo se nastaví jen při založení. */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower((string) config('pap.admin_email'));
        $password = (string) config('pap.admin_password');

        if ($email === '' || $password === '') {
            $this->command?->warn('PAP_ADMIN_EMAIL / PAP_ADMIN_PASSWORD nejsou nastavené – admin se nezaložil.');

            return;
        }

        $user = User::firstOrCreate(['email' => $email], ['name' => 'Administrátor', 'password' => $password, 'locale' => 'cs']);
        $user->syncRoles([...array_map(fn ($r) => Role::from($r), $user->roles()), Role::Admin]);
    }
}
