<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\Visit;
use App\Models\VisitPart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PapFixtures;
use Tests\TestCase;

/** Příprava na 6.1 – opakovaná synchronizace z telefonu nevytváří duplicity */
class OfflineSyncTest extends TestCase
{
    use PapFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedPap();
    }

    public function test_repeated_visit_and_photo_upload_do_not_duplicate(): void
    {
        $ticket = $this->makeTicket($this->locA, $this->tech1);
        $visitUuid = $this->uuid();
        $photoUuid = $this->uuid();
        $payload = [
            'arrived_at' => now()->subHour()->toIso8601String(), 'km' => 8, 'work_description' => 'Servis', 'test_result' => 'ok',
            'parts' => [['uuid' => $this->uuid(), 'name' => 'Pojistka']],
        ];

        Sanctum::actingAs($this->tech1);
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$visitUuid", $payload)->assertCreated();
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$visitUuid", $payload)->assertOk();

        for ($i = 0; $i < 2; $i++) {
            $this->post('/api/v1/attachments', [
                'uuid' => $photoUuid, 'attachable_type' => 'visit', 'attachable_id' => $visitUuid,
                'file' => UploadedFile::fake()->image('a.jpg'),
            ], ['Accept' => 'application/json'])->assertSuccessful();
        }

        $this->postJson("/api/v1/visits/$visitUuid/submit")->assertOk();
        $this->postJson("/api/v1/visits/$visitUuid/submit")->assertOk();

        $this->assertSame(1, Visit::count());
        $this->assertSame(1, VisitPart::count());
        $this->assertSame(1, Attachment::count());
        $this->assertCount(1, Storage::disk('local')->allFiles('attachments'));
    }

    public function test_stale_offline_update_does_not_overwrite_newer_data(): void
    {
        $ticket = $this->makeTicket($this->locA, $this->tech1);
        $uuid = $this->uuid();

        Sanctum::actingAs($this->tech1);
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$uuid", ['km' => 20, 'client_updated_at' => '2026-11-25T10:00:00Z'])->assertCreated();
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$uuid", ['km' => 5, 'client_updated_at' => '2026-11-25T09:00:00Z'])
            ->assertOk()->assertJsonPath('data.km', 20);
    }

    public function test_parts_list_is_replaced_by_uuid(): void
    {
        $ticket = $this->makeTicket($this->locA, $this->tech1);
        $uuid = $this->uuid();
        [$p1, $p2] = [$this->uuid(), $this->uuid()];

        Sanctum::actingAs($this->tech1);
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$uuid", ['parts' => [['uuid' => $p1, 'name' => 'A'], ['uuid' => $p2, 'name' => 'B']]]);
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$uuid", ['parts' => [['uuid' => $p2, 'name' => 'B2', 'quantity' => 3]]])
            ->assertOk()->assertJsonCount(1, 'data.parts')->assertJsonPath('data.parts.0.name', 'B2');
    }

    public function test_repeated_ticket_form_submit_creates_one_ticket(): void
    {
        Sanctum::actingAs($this->customerA);
        $uuid = $this->uuid();
        $first = $this->postJson('/api/v1/tickets', ['uuid' => $uuid, 'location_id' => $this->locA->id, 'title' => 'X'])->json('data.id');
        $second = $this->postJson('/api/v1/tickets', ['uuid' => $uuid, 'location_id' => $this->locA->id, 'title' => 'X'])->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Ticket::count());
    }
}
