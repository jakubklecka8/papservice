<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PapFixtures;
use Tests\TestCase;

/** Kontrolní body 2.3, 3.1 a 3.2 – kdo co vidí a smí */
class AccessControlTest extends TestCase
{
    use PapFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedPap();
    }

    // --- 2.3 ---

    public function test_technician_cannot_open_ticket_not_assigned_to_him(): void
    {
        $mine = $this->makeTicket($this->locA, $this->tech1);
        $other = $this->makeTicket($this->locA, $this->tech2);

        Sanctum::actingAs($this->tech1);
        $this->getJson("/api/v1/tickets/{$mine->id}")->assertOk();
        $this->getJson("/api/v1/tickets/{$other->id}")->assertForbidden();
        $this->putJson("/api/v1/tickets/{$other->id}/visits/{$this->uuid()}", ['km' => 1])->assertForbidden();

        $ids = collect($this->getJson('/api/v1/tickets')->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($other->id));
    }

    public function test_partner_has_read_only_access(): void
    {
        $ticket = $this->makeTicket($this->locA, $this->tech1);
        $algTicket = $this->makeTicket($this->locAlg);

        Sanctum::actingAs($this->spolecnik);
        $this->getJson("/api/v1/tickets/{$ticket->id}")->assertOk();
        $this->getJson("/api/v1/tickets/{$algTicket->id}")->assertOk();
        $this->getJson('/api/v1/tickets')->assertOk()->assertJsonCount(2, 'data');

        $this->postJson('/api/v1/tickets', ['location_id' => $this->locA->id, 'title' => 'X'])->assertForbidden();
        $this->patchJson("/api/v1/tickets/{$ticket->id}", ['title' => 'Změna'])->assertForbidden();
        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", ['technician_id' => $this->tech2->id])->assertForbidden();
        $this->postJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'cancelled'])->assertForbidden();
    }

    public function test_dispatcher_only_manages_own_projects(): void
    {
        $algTicket = $this->makeTicket($this->locAlg);

        Sanctum::actingAs($this->dispecer);
        $this->getJson("/api/v1/tickets/{$algTicket->id}")->assertForbidden();
        $this->postJson('/api/v1/tickets', ['location_id' => $this->locAlg->id, 'title' => 'X'])->assertForbidden();
    }

    public function test_combined_roles_technician_and_dispatcher(): void
    {
        $this->tech1->syncRoles([Role::Technik, Role::Dispecer]);
        $foreign = $this->makeTicket($this->locA, $this->tech2);

        Sanctum::actingAs($this->tech1->fresh());
        // jako dispečer projektu ZAS vidí i cizí zakázku a smí ji přeřadit
        $this->getJson("/api/v1/tickets/{$foreign->id}")->assertOk();
        $this->postJson("/api/v1/tickets/{$foreign->id}/assign", ['technician_id' => $this->tech1->id])->assertOk();
    }

    // --- 3.1 / 3.2 ---

    public function test_customer_creates_ticket_with_photo_and_sees_status(): void
    {
        Sanctum::actingAs($this->customerA);
        $id = $this->postJson('/api/v1/tickets', [
            'location_id' => $this->locA->id, 'title' => 'Displej nesvítí', 'priority' => 'urgent',
        ])->assertCreated()
            ->assertJsonPath('data.source', 'portal')
            ->assertJsonPath('data.priority', 'normal') // prioritu určuje dispečer
            ->json('data.id');

        $this->post('/api/v1/attachments', [
            'uuid' => $this->uuid(), 'attachable_type' => 'ticket', 'attachable_id' => $id,
            'file' => UploadedFile::fake()->image('displej.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.customer_visible', false);

        $this->getJson("/api/v1/tickets/$id")
            ->assertOk()
            ->assertJsonPath('data.status', 'new')
            ->assertJsonCount(1, 'data.attachments')
            ->assertJsonMissingPath('data.visits')
            ->assertJsonMissingPath('data.reporter');

        // na cizí provozovnu založit nemůže
        $this->postJson('/api/v1/tickets', ['location_id' => $this->locB->id, 'title' => 'X'])->assertForbidden();
    }

    public function test_customer_a_cannot_open_ticket_or_download_attachment_of_customer_b(): void
    {
        $ticketB = $this->makeTicket($this->locB);

        Sanctum::actingAs($this->customerB);
        $attUuid = $this->post('/api/v1/attachments', [
            'uuid' => $this->uuid(), 'attachable_type' => 'ticket', 'attachable_id' => $ticketB->id,
            'file' => UploadedFile::fake()->image('b.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.uuid');
        $this->get("/api/v1/attachments/$attUuid/download")->assertOk();

        Sanctum::actingAs($this->customerA);
        $this->getJson("/api/v1/tickets/{$ticketB->id}")->assertForbidden();
        $this->getJson("/api/v1/attachments/$attUuid/download")->assertForbidden();
        $this->assertCount(0, $this->getJson('/api/v1/tickets')->json('data'));

        // ani přes přímou cestu k souboru
        $this->get('/storage/attachments/'.now()->format('Y/m')."/$attUuid.jpg")->assertNotFound();
    }

    public function test_customer_sees_only_approved_protocol_and_selected_photos(): void
    {
        $ticket = $this->makeTicket($this->locA, $this->tech1);
        $visitUuid = $this->uuid();

        Sanctum::actingAs($this->tech1);
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$visitUuid", [
            'arrived_at' => now()->subHour()->toIso8601String(), 'km' => 5, 'work_description' => 'Servis', 'test_result' => 'ok',
        ])->assertCreated();
        $hidden = $this->post('/api/v1/attachments', [
            'uuid' => $this->uuid(), 'attachable_type' => 'visit', 'attachable_id' => $visitUuid,
            'file' => UploadedFile::fake()->image('interni.jpg'),
        ], ['Accept' => 'application/json'])->json('data.uuid');
        $shown = $this->post('/api/v1/attachments', [
            'uuid' => $this->uuid(), 'attachable_type' => 'visit', 'attachable_id' => $visitUuid,
            'file' => UploadedFile::fake()->image('hotovo.jpg'),
        ], ['Accept' => 'application/json'])->json('data.uuid');
        $this->postJson("/api/v1/visits/$visitUuid/submit")->assertOk();

        Sanctum::actingAs($this->dispecer);
        $protocolId = $this->postJson("/api/v1/visits/$visitUuid/protocol", ['language' => 'cs'])->json('data.id');
        $this->patchJson("/api/v1/attachments/$shown", ['customer_visible' => true])->assertOk();

        // před schválením zákazník protokol nevidí
        Sanctum::actingAs($this->customerA);
        $this->getJson("/api/v1/protocols/$protocolId")->assertForbidden();
        $this->getJson("/api/v1/tickets/{$ticket->id}")->assertJsonCount(0, 'data.protocols');

        Sanctum::actingAs($this->dispecer);
        $this->postJson("/api/v1/protocols/$protocolId/approve")->assertOk();

        Sanctum::actingAs($this->customerA);
        $res = $this->getJson("/api/v1/tickets/{$ticket->id}")->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonCount(1, 'data.protocols');
        $this->assertSame([$shown], collect($res->json('data.attachments'))->pluck('uuid')->all());
        $this->getJson("/api/v1/protocols/$protocolId")->assertOk();
        $this->get("/api/v1/attachments/$shown/download")->assertOk();
        $this->getJson("/api/v1/attachments/$hidden/download")->assertForbidden();
        $this->getJson("/api/v1/visits/$visitUuid")->assertForbidden();
    }

    public function test_inactive_user_cannot_login_or_use_token(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'tech1@test.cz', 'password' => 'secret123'])
            ->assertOk()->assertJsonStructure(['token', 'user' => ['roles']]);

        $ticket = $this->makeTicket($this->locA, $this->tech1);
        $this->tech1->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', ['email' => 'tech1@test.cz', 'password' => 'secret123'])->assertStatus(422);

        Sanctum::actingAs($this->tech1->fresh());
        $this->getJson("/api/v1/tickets/{$ticket->id}")->assertForbidden();
    }
}
