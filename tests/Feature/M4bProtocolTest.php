<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PapFixtures;
use Tests\TestCase;

/** Protokol M4B (PROTOKOL – Wykonanych prac) s podpisy prstem a PDF ze serveru */
class M4bProtocolTest extends TestCase
{
    use PapFixtures, RefreshDatabase;

    // 1×1 PNG
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedPap();
        $this->zas->update(['protocol_template' => 'm4b']);
    }

    private function submittedVisit(array $extra = []): array
    {
        $ticket = $this->makeTicket($this->locA, $this->tech1);
        Sanctum::actingAs($this->tech1);
        $uuid = $this->uuid();
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$uuid", $extra + [
            'arrived_at' => '2026-11-26T08:00:00+01:00',
            'departed_at' => '2026-11-26T09:30:00+01:00',
            'km' => 12,
            'work_description' => 'Wymiana czytnika, test płatności – ąęśćżź',
            'test_result' => 'ok',
            'signer_name' => 'Jan Kowalski',
            'signature_technician' => self::PNG,
            'signature_customer' => self::PNG,
        ])->assertSuccessful()
            ->assertJsonPath('data.signer_name', 'Jan Kowalski')
            ->assertJsonPath('data.signature_customer', self::PNG);
        $this->postJson("/api/v1/visits/$uuid/submit")->assertOk();

        return [$ticket, $uuid];
    }

    public function test_m4b_protocol_has_old_layout_signatures_and_pdf(): void
    {
        [$ticket, $uuid] = $this->submittedVisit();

        Sanctum::actingAs($this->dispecer);
        $this->getJson("/api/v1/tickets/{$ticket->id}")->assertJsonPath('data.project.protocol_template', 'm4b');

        $p = $this->postJson("/api/v1/visits/$uuid/protocol", ['language' => 'pl'])->assertCreated()->json('data');
        $this->assertSame('m4b', $p['template']);
        $this->assertSame(self::PNG, $p['content']['signatures']['customer']);
        $this->assertNotNull($p['content']['signatures']['signed_at']);

        $this->get("/api/v1/protocols/{$p['id']}/html")->assertOk()
            ->assertSee('Wykonanych prac')->assertSee('Zlecajacy')->assertSee('Podpis zleceniodawcy')
            ->assertSee('Jan Kowalski')->assertSee('Z-BOX A (Z-A)')->assertSee('ąęśćżź');

        $res = $this->get("/api/v1/protocols/{$p['id']}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());

        $this->postJson("/api/v1/protocols/{$p['id']}/approve")->assertOk();
        $this->get("/api/v1/protocols/{$p['id']}/pdf")->assertOk();
        Storage::disk('local')->assertExists("protocols/{$p['id']}.pdf");
    }

    public function test_standard_protocol_pdf_and_invalid_signature_rejected(): void
    {
        $this->zas->update(['protocol_template' => 'standard']);
        [, $uuid] = $this->submittedVisit();

        Sanctum::actingAs($this->dispecer);
        $p = $this->postJson("/api/v1/visits/$uuid/protocol", ['language' => 'cs'])->assertCreated()->json('data');
        $this->assertSame('standard', $p['template']);
        $this->get("/api/v1/protocols/{$p['id']}/html")->assertOk()->assertSee('Servisní protokol')->assertSee('Podpis zákazníka');
        $this->assertStringStartsWith('%PDF', $this->get("/api/v1/protocols/{$p['id']}/pdf")->assertOk()->getContent());

        $ticket = $this->makeTicket($this->locA, $this->tech1);
        Sanctum::actingAs($this->tech1);
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/{$this->uuid()}", ['signature_customer' => 'javascript:alert(1)'])
            ->assertStatus(422);
    }
}
