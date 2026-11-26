<?php

namespace Tests\Feature;

use App\Models\Tariff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PapFixtures;
use Tests\TestCase;

/** Ceník, cena u zakázky a vyúčtování */
class BillingTest extends TestCase
{
    use PapFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedPap();
        Tariff::create(['project_id' => $this->zas->id, 'service_type' => 'servis', 'currency' => 'CZK', 'base_price' => 450, 'km_rate' => 2.5]);
    }

    private function doneTicket(float $km, string $date = '2026-11-20T09:00:00+01:00')
    {
        $ticket = $this->makeTicket($this->locA, $this->tech1);
        Sanctum::actingAs($this->tech1);
        $uuid = $this->uuid();
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$uuid", [
            'arrived_at' => $date, 'km' => $km, 'work_description' => 'x', 'test_result' => 'ok',
        ])->assertSuccessful();
        $this->postJson("/api/v1/visits/$uuid/submit")->assertOk();

        return $ticket;
    }

    public function test_price_from_tariff_and_manual_override(): void
    {
        $ticket = $this->doneTicket(40);

        // technik cenu nevidí
        $this->getJson("/api/v1/tickets/{$ticket->id}")->assertJsonMissingPath('data.price');

        Sanctum::actingAs($this->dispecer);
        $this->getJson("/api/v1/tickets/{$ticket->id}")
            ->assertJsonPath('data.price.currency', 'CZK')
            ->assertJsonPath('data.price.km_amount', 100)
            ->assertJsonPath('data.price.total', 550);

        $this->patchJson("/api/v1/tickets/{$ticket->id}/price", ['price_base' => 600, 'price_extra' => 150])
            ->assertOk()->assertJsonPath('data.total', 850);
        $this->patchJson("/api/v1/tickets/{$ticket->id}/price", ['price_total' => 1000, 'price_note' => 'dohoda'])
            ->assertOk()->assertJsonPath('data.total', 1000)->assertJsonPath('data.computed', 850)->assertJsonPath('data.manual_total', true);
    }

    public function test_tariff_is_frozen_on_close_and_billing_report(): void
    {
        $ticket = $this->doneTicket(10);
        $other = $this->doneTicket(20, '2026-10-05T09:00:00+02:00'); // jiný měsíc

        Sanctum::actingAs($this->dispecer);
        $vuuid = $ticket->visits()->first()->uuid;
        $pid = $this->postJson("/api/v1/visits/$vuuid/protocol", ['language' => 'cs'])->json('data.id');
        $this->postJson("/api/v1/protocols/$pid/approve")->assertOk();

        Tariff::first()->update(['base_price' => 999]);
        $this->getJson("/api/v1/tickets/{$ticket->id}")->assertJsonPath('data.price.base', 450)->assertJsonPath('data.price.total', 475);

        $r = $this->getJson('/api/v1/billing?from=2026-11-01&to=2026-11-30')->assertOk();
        $r->assertJsonCount(1, 'data')->assertJsonPath('data.0.total', 475)->assertJsonPath('totals.0.total', 475);
        $this->getJson('/api/v1/billing?from=2026-10-01&to=2026-11-30')->assertJsonCount(2, 'data')
            ->assertJsonPath('totals.0.total', 475 + 999 + 50);

        $csv = $this->get('/api/v1/billing/export?from=2026-11-01&to=2026-11-30')->assertOk()->streamedContent();
        $this->assertStringContainsString('475', $csv);
        $this->assertStringContainsString($ticket->fresh()->number, $csv);

        Sanctum::actingAs($this->tech1);
        $this->getJson('/api/v1/billing')->assertForbidden();
    }

    public function test_only_admin_edits_tariffs(): void
    {
        Sanctum::actingAs($this->dispecer);
        $this->getJson('/api/v1/tariffs')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/tariffs', ['project_id' => $this->zas->id, 'currency' => 'CZK', 'base_price' => 1, 'km_rate' => 1])->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/tariffs', ['project_id' => $this->zas->id, 'service_type' => 'instalace', 'currency' => 'EUR', 'base_price' => 120, 'km_rate' => 0])->assertCreated();
        $this->postJson('/api/v1/tariffs', ['project_id' => $this->zas->id, 'service_type' => 'servis', 'currency' => 'CZK', 'base_price' => 1, 'km_rate' => 1])->assertStatus(422);
    }
}
