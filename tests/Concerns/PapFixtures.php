<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Location;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Str;

trait PapFixtures
{
    protected Project $zas;

    protected Project $alg;

    protected Location $locA;

    protected Location $locB;

    protected Location $locAlg;

    protected User $admin;

    protected User $dispecer;

    protected User $tech1;

    protected User $tech2;

    protected User $spolecnik;

    protected User $customerA;

    protected User $customerB;

    protected function seedPap(): void
    {
        $this->zas = Project::create(['code' => 'ZAS', 'name' => 'Zásilkovna']);
        $this->alg = Project::create(['code' => 'ALG', 'name' => 'Allegro']);

        $this->locA = Location::create(['project_id' => $this->zas->id, 'code' => 'Z-A', 'name' => 'Z-BOX A', 'city' => 'Ostrava']);
        $this->locB = Location::create(['project_id' => $this->zas->id, 'code' => 'Z-B', 'name' => 'Z-BOX B', 'city' => 'Praha']);
        $this->locAlg = Location::create(['project_id' => $this->alg->id, 'code' => 'A-1', 'name' => 'Allegro 1', 'city' => 'Katowice', 'country' => 'PL']);

        $this->admin = $this->makeUser('admin', [Role::Admin]);
        $this->dispecer = $this->makeUser('dispecer', [Role::Dispecer]);
        $this->tech1 = $this->makeUser('tech1', [Role::Technik]);
        $this->tech2 = $this->makeUser('tech2', [Role::Technik]);
        $this->spolecnik = $this->makeUser('spolecnik', [Role::Spolecnik]);
        $this->customerA = $this->makeUser('custa', [Role::Zakaznik]);
        $this->customerB = $this->makeUser('custb', [Role::Zakaznik]);

        $this->dispecer->projects()->attach($this->zas->id);
        $this->tech1->projects()->attach($this->zas->id);
        $this->tech2->projects()->attach($this->zas->id);
        $this->customerA->locations()->attach($this->locA->id);
        $this->customerB->locations()->attach($this->locB->id);
    }

    /** @param list<Role> $roles */
    protected function makeUser(string $name, array $roles): User
    {
        $user = User::create(['name' => ucfirst($name), 'email' => $name.'@test.cz', 'password' => 'secret123', 'is_active' => true]);
        $user->syncRoles($roles);

        return $user->fresh();
    }

    protected function makeTicket(Location $location, ?User $technician = null): Ticket
    {
        return Ticket::create([
            'project_id' => $location->project_id,
            'location_id' => $location->id,
            'title' => 'Nejde otevřít schránka',
            'status' => $technician ? TicketStatus::Assigned : TicketStatus::New,
            'assigned_technician_id' => $technician?->id,
        ]);
    }

    protected function uuid(): string
    {
        return (string) Str::uuid();
    }
}
