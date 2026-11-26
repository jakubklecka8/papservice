<?php

namespace Tests\Feature;

use App\Models\ChecklistTemplate;
use App\Models\Device;
use App\Models\Schedule;
use App\Models\Ticket;
use App\Services\ScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PapFixtures;
use Tests\TestCase;

/** M4 – zařízení, kontrolní seznamy, SLA, harmonogram a plánování (4.1) */
class PlanningDevicesTest extends TestCase
{
    use PapFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedPap();
    }

    public function test_admin_creates_project_with_sla_and_checklist(): void
    {
        Sanctum::actingAs($this->admin);
        $pid = $this->postJson('/api/v1/admin/projects', [
            'code' => 'TEST', 'name' => 'Testovací', 'service_types' => ['servis', 'udrzba'], 'countries' => ['cz'],
            'sla_hours' => ['urgent' => 4, 'normal' => 48],
            'end_customers' => [['code' => 'ec1', 'name' => 'Koncový 1']],
        ])->assertCreated()->assertJsonPath('data.countries.0', 'CZ')->assertJsonPath('data.end_customers.0.code', 'EC1')->json('data.id');

        $this->postJson('/api/v1/checklists', [
            'project_id' => $pid, 'service_type' => 'servis', 'name' => 'Servis – kontrola',
            'items' => [['text' => 'Kontrola napájení', 'required' => true], ['text' => 'Čištění']],
        ])->assertCreated();

        Carbon::setTestNow('2026-11-26 10:00:00');
        $tid = $this->postJson('/api/v1/tickets', ['project_id' => $pid, 'title' => 'Porucha'])->assertCreated()->json('data.id');
        $t = Ticket::find($tid);
        $this->assertSame('2026-11-28 10:00:00', $t->due_at->format('Y-m-d H:i:s'));
        $this->assertNotNull($t->checklist_template_id);
        $this->getJson("/api/v1/tickets/$tid")->assertJsonPath('data.checklist.items.0.text', 'Kontrola napájení');
        Carbon::setTestNow();

        Sanctum::actingAs($this->dispecer);
        $this->postJson('/api/v1/admin/projects', ['code' => 'X', 'name' => 'X', 'service_types' => ['servis']])->assertForbidden();
    }

    public function test_checklist_required_on_submit_and_device_history(): void
    {
        $tpl = ChecklistTemplate::create(['project_id' => $this->zas->id, 'service_type' => 'servis', 'name' => 'Z-BOX',
            'items' => [['id' => 'a1', 'text' => 'Test dveří', 'required' => true], ['id' => 'a2', 'text' => 'Čištění', 'required' => false]]]);

        Sanctum::actingAs($this->dispecer);
        $deviceId = $this->postJson('/api/v1/devices', [
            'project_id' => $this->zas->id, 'location_id' => $this->locA->id, 'type' => 'Z-BOX', 'model' => 'ZB-60', 'serial_number' => 'SN-1', 'installed_at' => '2026-01-10',
        ])->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/devices/$deviceId", ['serial_number' => 'SN-2', 'handover_at' => '2026-01-11', 'handover_to' => 'Pan Novák'])->assertOk();

        $ticket = $this->makeTicket($this->locA, $this->tech1);
        $this->assertSame($tpl->id, $ticket->fresh()->checklist_template_id);
        $this->patchJson("/api/v1/tickets/{$ticket->id}", ['device_id' => $deviceId])->assertOk();

        Sanctum::actingAs($this->tech1);
        $this->getJson("/api/v1/tickets/{$ticket->id}")->assertJsonPath('data.device.serial_number', 'SN-2');
        $uuid = $this->uuid();
        $base = ['arrived_at' => '2026-11-20T09:00:00+01:00', 'km' => 5, 'work_description' => 'Servis', 'test_result' => 'ok'];
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$uuid", $base + ['checklist' => [['id' => 'a2', 'result' => 'ok']]])->assertSuccessful();
        $this->postJson("/api/v1/visits/$uuid/submit")->assertStatus(422)->assertJsonFragment(['missing' => ['checklist']]);

        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$uuid", $base + ['checklist' => [
            ['id' => 'a1', 'result' => 'nok', 'note' => 'drhne'], ['id' => 'a2', 'result' => 'ok'], ['id' => 'xx', 'result' => 'ok'],
        ]])->assertSuccessful()->assertJsonCount(2, 'data.checklist')->assertJsonPath('data.checklist.0.text', 'Test dveří');
        $this->postJson("/api/v1/visits/$uuid/submit")->assertOk();

        Sanctum::actingAs($this->dispecer);
        $d = $this->getJson("/api/v1/devices/$deviceId")->assertOk()->json('data');
        $this->assertSame(['replace', 'service', 'handover', 'install'], collect($d['events'])->pluck('type')->all());
        $this->assertSame('SN-1', $d['events'][0]['old_serial']);

        $p = $this->postJson("/api/v1/visits/$uuid/protocol", ['language' => 'cs'])->json('data.id');
        $this->get("/api/v1/protocols/$p/html")->assertSee('Test dveří')->assertSee('Závada')->assertSee('S/N SN-2');

        $this->assertStringContainsString('SN-2', $this->get('/api/v1/devices/export')->assertOk()->streamedContent());
        Sanctum::actingAs($this->tech2);
        $this->getJson("/api/v1/devices/$deviceId")->assertForbidden();
    }

    public function test_schedule_generates_ticket_once_and_advances(): void
    {
        $tpl = ChecklistTemplate::create(['project_id' => $this->zas->id, 'service_type' => 'profilaxe', 'name' => 'Profilaxe', 'items' => [['id' => 'p1', 'text' => 'Kontrola']]]);
        $s = Schedule::create([
            'project_id' => $this->zas->id, 'location_id' => $this->locA->id, 'checklist_template_id' => $tpl->id, 'technician_id' => $this->tech1->id,
            'service_type' => 'profilaxe', 'title' => 'Profilaxe Z-BOX A', 'interval_months' => 3, 'next_due_on' => '2026-12-05', 'lead_days' => 14,
        ]);
        $svc = app(ScheduleService::class);

        $this->assertSame(0, $svc->run(Carbon::parse('2026-11-15', 'Europe/Prague')));
        $this->assertSame(1, $svc->run(Carbon::parse('2026-11-22', 'Europe/Prague')));
        $this->assertSame(0, $svc->run(Carbon::parse('2026-11-23', 'Europe/Prague')));

        $t = Ticket::where('schedule_id', $s->id)->sole();
        $this->assertSame('profilaxe', $t->service_type);
        $this->assertSame('assigned', $t->status->value);
        $this->assertSame($this->tech1->id, $t->assigned_technician_id);
        $this->assertSame($tpl->id, $t->checklist_template_id);
        $this->assertSame('2026-12-05', $t->due_at->timezone('Europe/Prague')->toDateString());
        $this->assertSame('2027-03-05', $s->fresh()->next_due_on->toDateString());
    }

    public function test_planning_calendar_plan_and_absence(): void
    {
        $ticket = $this->makeTicket($this->locA);
        Sanctum::actingAs($this->dispecer);

        $cal = $this->getJson('/api/v1/planning?from=2026-11-23&to=2026-11-29')->assertOk();
        $cal->assertJsonFragment(['id' => $ticket->id]);
        $this->assertCount(0, $cal->json('planned'));

        $this->postJson("/api/v1/tickets/{$ticket->id}/plan", ['technician_id' => $this->tech1->id, 'planned_at' => '2026-11-25T08:00:00+01:00'])
            ->assertOk()->assertJsonPath('data.technician_id', $this->tech1->id);
        $this->assertSame('assigned', $ticket->fresh()->status->value);

        $this->postJson('/api/v1/absences', ['user_id' => $this->tech2->id, 'date_from' => '2026-11-26', 'date_to' => '2026-11-27', 'note' => 'Dovolená'])->assertCreated();
        $cal = $this->getJson('/api/v1/planning?from=2026-11-23&to=2026-11-29')->assertOk();
        $this->assertSame($ticket->id, $cal->json('planned.0.id'));
        $this->assertCount(0, $cal->json('unplanned'));
        $this->assertSame('Dovolená', $cal->json('absences.0.note'));

        $this->postJson("/api/v1/tickets/{$ticket->id}/plan", ['planned_at' => null])->assertOk();
        $this->assertNull($ticket->fresh()->planned_at);

        Sanctum::actingAs($this->tech1);
        $this->getJson('/api/v1/planning?from=2026-11-23&to=2026-11-29')->assertForbidden();
    }
}
