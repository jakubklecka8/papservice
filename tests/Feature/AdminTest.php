<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PapFixtures;
use Tests\TestCase;

/** Správa uživatelů, provozoven a souhrn pro portál */
class AdminTest extends TestCase
{
    use PapFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPap();
    }

    public function test_admin_creates_technician_who_can_log_in(): void
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/v1/admin/users', [
            'name' => 'Nový Technik', 'email' => 'Novy@Test.cz', 'password' => 'heslo1234',
            'roles' => ['technik'], 'project_ids' => [$this->zas->id], 'locale' => 'pl',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'novy@test.cz')
            ->assertJsonPath('data.roles', ['technik'])
            ->json('data.id');

        $this->getJson('/api/v1/technicians?project_id='.$this->zas->id)
            ->assertOk()->assertJsonFragment(['id' => $id]);

        auth()->forgetGuards();
        $this->postJson('/api/v1/auth/login', ['email' => 'novy@test.cz', 'password' => 'heslo1234'])
            ->assertOk()->assertJsonPath('user.locale', 'pl');
    }

    public function test_admin_updates_roles_and_deactivates_user(): void
    {
        $this->tech1->createToken('telefon');
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/v1/admin/users/{$this->tech1->id}", ['roles' => ['technik', 'dispecer']])
            ->assertOk()->assertJsonPath('data.roles', ['technik', 'dispecer']);

        $this->patchJson("/api/v1/admin/users/{$this->tech1->id}", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertSame(0, $this->tech1->tokens()->count());
    }

    public function test_admin_cannot_lock_himself_out(): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/admin/users/{$this->admin->id}", ['roles' => ['dispecer']])->assertStatus(422);
        $this->patchJson("/api/v1/admin/users/{$this->admin->id}", ['is_active' => false])->assertStatus(422);
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        Sanctum::actingAs($this->dispecer);
        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->postJson('/api/v1/admin/users', ['name' => 'X', 'email' => 'x@x.cz', 'password' => 'heslo1234', 'roles' => ['admin']])
            ->assertForbidden();
        $this->assertSame(0, User::where('email', 'x@x.cz')->count());
    }

    public function test_dispatcher_manages_locations_only_in_own_project(): void
    {
        Sanctum::actingAs($this->dispecer);
        $id = $this->postJson('/api/v1/locations', [
            'project_id' => $this->zas->id, 'code' => 'Z-NEW', 'name' => 'Z-BOX Nový', 'city' => 'Opava',
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/locations/$id", ['name' => 'Z-BOX Opava'])->assertOk()->assertJsonPath('data.name', 'Z-BOX Opava');
        $this->postJson('/api/v1/locations', ['project_id' => $this->zas->id, 'code' => 'Z-NEW', 'name' => 'Duplicita'])->assertStatus(422);

        $this->postJson('/api/v1/locations', ['project_id' => $this->alg->id, 'code' => 'A-9', 'name' => 'Cizí'])->assertForbidden();
        $this->patchJson("/api/v1/locations/{$this->locAlg->id}", ['name' => 'Cizí'])->assertForbidden();

        Sanctum::actingAs($this->tech1);
        $this->postJson('/api/v1/locations', ['project_id' => $this->zas->id, 'code' => 'T-1', 'name' => 'X'])->assertForbidden();
    }

    public function test_summary_counts_only_visible_tickets(): void
    {
        $this->makeTicket($this->locA);
        $this->makeTicket($this->locA, $this->tech1);
        $this->makeTicket($this->locAlg);

        Sanctum::actingAs($this->dispecer);
        $this->getJson('/api/v1/tickets/summary')->assertOk()
            ->assertJsonPath('data.new', 1)
            ->assertJsonPath('data.assigned', 1);

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/tickets/summary')->assertJsonPath('data.new', 2);
    }
}
