<?php

namespace Tests\Feature;

use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PapFixtures;
use Tests\TestCase;

/** M2 – pilotní provoz Zásilkovna Servis (kontrolní body 2.1 a 2.2) */
class PilotFlowTest extends TestCase
{
    use PapFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedPap();
    }

    public function test_full_flow_from_ticket_to_approved_protocol(): void
    {
        // Dispečer založí a přidělí požadavek
        Sanctum::actingAs($this->dispecer);
        $ticketId = $this->postJson('/api/v1/tickets', [
            'location_id' => $this->locA->id,
            'title' => 'Zaseknutá schránka č. 12',
            'description' => 'Zákazník nemůže vyzvednout zásilku.',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.source', 'manual')
            ->json('data.id');

        $number = $this->getJson("/api/v1/tickets/$ticketId")->json('data.number');
        $this->assertMatchesRegularExpression('/^PAP-\d{4}-\d{6}$/', $number);

        $this->postJson("/api/v1/tickets/$ticketId/assign", ['technician_id' => $this->tech1->id])
            ->assertOk()->assertJsonPath('data.status', 'assigned')->assertJsonPath('data.technician.id', $this->tech1->id);

        // 2.1 Technik vidí vlastní zakázku a uloží stav, fotku, test, čas a km
        Sanctum::actingAs($this->tech1);
        $this->getJson('/api/v1/tickets?mine=1')->assertOk()->assertJsonCount(1, 'data');

        $visitUuid = $this->uuid();
        $partUuid = $this->uuid();
        $this->putJson("/api/v1/tickets/$ticketId/visits/$visitUuid", [
            'arrived_at' => '2026-11-25T09:00:00+01:00',
            'departed_at' => '2026-11-25T09:45:00+01:00',
            'km' => 42.5,
            'work_description' => 'Výměna zámku schránky 12, test otevření.',
            'repair_description' => 'Ulomený jazýček zámku.',
            'test_result' => 'ok',
            'instruction_ref' => 'ZAS-ZAMEK-v3',
            'parts' => [[
                'uuid' => $partUuid, 'name' => 'Zámek schránky', 'part_number' => 'ZL-100', 'quantity' => 1,
                'serial_removed' => 'SN-OLD-1', 'serial_installed' => 'SN-NEW-1',
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.minutes_on_site', 45)
            ->assertJsonPath('data.arrived_at', '2026-11-25T08:00:00+00:00')
            ->assertJsonPath('data.status', 'draft');

        $this->getJson("/api/v1/tickets/$ticketId")->assertJsonPath('data.status', 'in_progress');

        $this->post('/api/v1/attachments', [
            'uuid' => $photoUuid = $this->uuid(),
            'attachable_type' => 'visit',
            'attachable_id' => $visitUuid,
            'file' => UploadedFile::fake()->image('zamek.jpg', 800, 600),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.kind', 'photo');

        $this->postJson("/api/v1/visits/$visitUuid/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->getJson("/api/v1/tickets/$ticketId")->assertJsonPath('data.status', 'completed');

        // Po odeslání už technik návštěvu nepřepíše
        $this->putJson("/api/v1/tickets/$ticketId/visits/$visitUuid", ['km' => 999])->assertStatus(409);

        // 2.2 Dispečer vytvoří protokol v polštině a schválí ho
        Sanctum::actingAs($this->dispecer);
        $protocol = $this->postJson("/api/v1/visits/$visitUuid/protocol", ['language' => 'pl'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.content.labels.title', 'Protokół serwisowy')
            ->json('data');

        // Protokol odpovídá údajům v zakázce
        $this->assertSame($number, $protocol['content']['ticket']['number']);
        $this->assertEquals(42.5, $protocol['content']['visit']['km']);
        $this->assertSame(45, $protocol['content']['visit']['minutes_on_site']);
        $this->assertSame('ok', $protocol['content']['visit']['test_result']);
        $this->assertSame('SN-NEW-1', $protocol['content']['parts'][0]['serial_installed']);
        $this->assertSame([$photoUuid], $protocol['content']['photos']);

        $this->postJson("/api/v1/protocols/{$protocol['id']}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->getJson("/api/v1/tickets/$ticketId")
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.visits.0.status', 'approved');

        $this->get("/api/v1/protocols/{$protocol['id']}/html")->assertOk()->assertSee('Protokół serwisowy')->assertSee($number);
    }

    public function test_protocol_cannot_be_approved_when_visit_changed_after_draft(): void
    {
        $ticket = $this->makeTicket($this->locA, $this->tech1);
        $uuid = $this->uuid();

        Sanctum::actingAs($this->tech1);
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$uuid", [
            'arrived_at' => now()->subHour()->toIso8601String(), 'km' => 10, 'work_description' => 'Kontrola', 'test_result' => 'ok',
        ])->assertCreated();
        $this->postJson("/api/v1/visits/$uuid/submit")->assertOk();

        Sanctum::actingAs($this->dispecer);
        $protocolId = $this->postJson("/api/v1/visits/$uuid/protocol", ['language' => 'cs'])->assertCreated()->json('data.id');

        // Data návštěvy se mezitím změní (např. oprava v DB)
        Visit::where('uuid', $uuid)->update(['km' => 11]);

        $this->postJson("/api/v1/protocols/$protocolId/approve")->assertStatus(409);
    }

    public function test_submit_requires_mandatory_fields(): void
    {
        $ticket = $this->makeTicket($this->locA, $this->tech1);
        $uuid = $this->uuid();

        Sanctum::actingAs($this->tech1);
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$uuid", ['work_description' => 'Něco'])->assertCreated();
        $this->postJson("/api/v1/visits/$uuid/submit")
            ->assertStatus(422)
            ->assertJsonPath('missing', ['arrived_at', 'test_result', 'km']);
    }

    public function test_dispatcher_can_return_visit_and_technician_can_fix_it(): void
    {
        $ticket = $this->makeTicket($this->locA, $this->tech1);
        $uuid = $this->uuid();

        Sanctum::actingAs($this->tech1);
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$uuid", [
            'arrived_at' => now()->subHour()->toIso8601String(), 'km' => 10, 'work_description' => 'Kontrola', 'test_result' => 'ok',
        ]);
        $this->postJson("/api/v1/visits/$uuid/submit")->assertOk();

        Sanctum::actingAs($this->dispecer);
        $this->postJson("/api/v1/visits/$uuid/return", ['note' => 'Chybí fotka'])->assertOk()->assertJsonPath('data.status', 'returned');

        Sanctum::actingAs($this->tech1);
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$uuid", ['km' => 12])->assertOk()->assertJsonPath('data.km', 12);
        $this->postJson("/api/v1/visits/$uuid/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
    }
}
