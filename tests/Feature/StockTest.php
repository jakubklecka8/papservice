<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PapFixtures;
use Tests\TestCase;

/** Kontrolní bod 4.2 – převod do skladu technika a spotřeba na zakázce mění stav zásob */
class StockTest extends TestCase
{
    use PapFixtures, RefreshDatabase;

    private Warehouse $main;

    private Part $lock;

    private Part $psu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPap();
        $this->main = Warehouse::create(['code' => 'ZAS', 'name' => 'Sklad Zásilkovna', 'type' => 'project', 'project_id' => $this->zas->id]);
        $this->lock = Part::create(['part_number' => 'LOCK-1', 'name' => 'Zámek']);
        $this->psu = Part::create(['part_number' => 'PSU-24', 'name' => 'Zdroj 24 V', 'serialized' => true]);
    }

    private function qty(Warehouse $w, Part $p): float
    {
        Sanctum::actingAs($this->admin);
        $row = collect($this->getJson("/api/v1/stock?warehouse_id={$w->id}")->json('data'))->firstWhere('part.id', $p->id);

        return (float) ($row['qty'] ?? 0);
    }

    public function test_receipt_transfer_and_overdraw_protection(): void
    {
        $techWh = Warehouse::forTechnician($this->tech1);

        Sanctum::actingAs($this->dispecer);
        $this->postJson('/api/v1/stock/movements', ['type' => 'receipt', 'part_id' => $this->lock->id, 'quantity' => 10, 'to_warehouse_id' => $this->main->id])->assertCreated();
        $this->postJson('/api/v1/stock/movements', ['type' => 'transfer', 'part_id' => $this->lock->id, 'quantity' => 4, 'from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $techWh->id])->assertCreated();
        $this->postJson('/api/v1/stock/movements', ['type' => 'transfer', 'part_id' => $this->lock->id, 'quantity' => 7, 'from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $techWh->id])
            ->assertStatus(422)->assertJsonValidationErrors('quantity');
        // převod bez zdrojového skladu není převod
        $this->postJson('/api/v1/stock/movements', ['type' => 'transfer', 'part_id' => $this->lock->id, 'quantity' => 1, 'to_warehouse_id' => $techWh->id])->assertStatus(422);

        $this->assertSame(6.0, $this->qty($this->main, $this->lock));
        $this->assertSame(4.0, $this->qty($techWh, $this->lock));
    }

    public function test_repeated_movement_form_is_idempotent(): void
    {
        Sanctum::actingAs($this->dispecer);
        $uuid = $this->uuid();
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/stock/movements', ['uuid' => $uuid, 'type' => 'receipt', 'part_id' => $this->lock->id, 'quantity' => 5, 'to_warehouse_id' => $this->main->id])->assertCreated();
        }
        $this->assertSame(5.0, $this->qty($this->main, $this->lock));
    }

    public function test_visit_consumption_decreases_technician_stock_without_doubling(): void
    {
        $techWh = Warehouse::forTechnician($this->tech1);
        StockMovement::create(['type' => 'receipt', 'part_id' => $this->lock->id, 'quantity' => 5, 'to_warehouse_id' => $techWh->id]);
        $ticket = $this->makeTicket($this->locA, $this->tech1);
        $visitUuid = $this->uuid();
        $partUuid = $this->uuid();

        Sanctum::actingAs($this->tech1);
        $base = ['arrived_at' => now()->subHour()->toIso8601String(), 'km' => 5, 'work_description' => 'Výměna', 'test_result' => 'ok'];
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$visitUuid", $base + [
            'parts' => [['uuid' => $partUuid, 'part_id' => $this->lock->id, 'name' => 'Zámek', 'quantity' => 2]],
        ])->assertCreated();

        // před odesláním se ze skladu nic neodečte
        $this->assertSame(5.0, $this->qty($techWh, $this->lock));

        Sanctum::actingAs($this->tech1);
        $this->postJson("/api/v1/visits/$visitUuid/submit")->assertOk();
        $this->assertSame(3.0, $this->qty($techWh, $this->lock));

        // vráceno, technik opraví množství na 1 a znovu odešle → stav 4, ne 2
        Sanctum::actingAs($this->dispecer);
        $this->postJson("/api/v1/visits/$visitUuid/return", ['note' => 'Opravit množství'])->assertOk();
        Sanctum::actingAs($this->tech1);
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$visitUuid", [
            'parts' => [['uuid' => $partUuid, 'part_id' => $this->lock->id, 'name' => 'Zámek', 'quantity' => 1]],
        ])->assertOk();
        $this->postJson("/api/v1/visits/$visitUuid/submit")->assertOk();

        $this->assertSame(4.0, $this->qty($techWh, $this->lock));
        $this->assertSame(1, StockMovement::where('type', 'consumption')->count());
        $this->assertSame($ticket->id, StockMovement::where('type', 'consumption')->value('ticket_id'));
    }

    public function test_ticket_flags_missing_serial_numbers(): void
    {
        $ticket = $this->makeTicket($this->locA, $this->tech1);
        $uuid = $this->uuid();

        Sanctum::actingAs($this->tech1);
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$uuid", ['parts' => [
            ['uuid' => $this->uuid(), 'part_id' => $this->psu->id, 'name' => 'Zdroj 24 V', 'serial_removed' => 'OLD-1'],
            ['uuid' => $this->uuid(), 'part_id' => $this->lock->id, 'name' => 'Zámek'],
        ]])->assertCreated();

        Sanctum::actingAs($this->dispecer);
        $parts = collect($this->getJson("/api/v1/tickets/{$ticket->id}")->json('data.visits.0.parts'))->keyBy('name');
        $this->assertTrue($parts['Zdroj 24 V']['serialized']);
        $this->assertFalse($parts['Zdroj 24 V']['missing_serial_removed']);
        $this->assertTrue($parts['Zdroj 24 V']['missing_serial_installed']);
        $this->assertFalse($parts['Zámek']['missing_serial_installed']); // díl bez S/N se neoznačuje
    }

    public function test_technician_sees_only_own_warehouse_and_cannot_move_stock(): void
    {
        $own = Warehouse::forTechnician($this->tech1);
        $other = Warehouse::forTechnician($this->tech2);

        Sanctum::actingAs($this->tech1);
        $this->assertSame([$own->id], collect($this->getJson('/api/v1/warehouses')->json('data'))->pluck('id')->all());
        $this->getJson('/api/v1/stock?warehouse_id=mine')->assertOk()->assertJsonPath('warehouse_ids', [$own->id]);
        $this->getJson("/api/v1/stock?warehouse_id={$other->id}")->assertForbidden();
        $this->getJson("/api/v1/stock?warehouse_id={$this->main->id}")->assertForbidden();
        $this->postJson('/api/v1/stock/movements', ['type' => 'receipt', 'part_id' => $this->lock->id, 'quantity' => 1, 'to_warehouse_id' => $own->id])->assertForbidden();
        $this->getJson('/api/v1/parts')->assertOk(); // katalog vidí kvůli výběru dílu
    }

    public function test_reorder_suggestions_below_minimum(): void
    {
        Sanctum::actingAs($this->dispecer);
        $this->putJson('/api/v1/stock/minimums', ['warehouse_id' => $this->main->id, 'part_id' => $this->lock->id, 'min_qty' => 5])->assertOk();
        $this->postJson('/api/v1/stock/movements', ['type' => 'receipt', 'part_id' => $this->lock->id, 'quantity' => 2, 'to_warehouse_id' => $this->main->id]);

        $this->getJson('/api/v1/stock/reorder')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.part.part_number', 'LOCK-1')
            ->assertJsonPath('data.0.suggested', 3);

        $this->getJson("/api/v1/stock?warehouse_id={$this->main->id}")->assertJsonPath('data.0.below_min', true);
    }
}
