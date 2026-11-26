<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Expense;
use App\Models\PayRate;
use App\Models\Tariff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PapFixtures;
use Tests\TestCase;

/** Krok 11 – výdaje s doklady, odměny techniků, vyúčtování a náklady projektů */
class ExpensePayrollTest extends TestCase
{
    use PapFixtures, RefreshDatabase;

    private $ekonom;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedPap();
        $this->ekonom = $this->makeUser('ekonom', [Role::Ekonom]);
    }

    private function addExpense(array $extra = []): array
    {
        return $this->post('/api/v1/expenses', $extra + [
            'uuid' => $this->uuid(), 'category' => 'fuel', 'amount' => 1250.5, 'currency' => 'CZK', 'spent_on' => '2026-11-10',
            'liters' => 30, 'paid_with' => 'own', 'description' => 'Tankování Shell',
            'receipt' => UploadedFile::fake()->image('uctenka.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
    }

    public function test_expense_workflow_and_permissions(): void
    {
        $ticket = $this->makeTicket($this->locA, $this->tech1);
        Sanctum::actingAs($this->tech1);
        $e = $this->addExpense(['ticket_id' => $ticket->id]);
        $this->assertSame('submitted', $e['status']);
        $this->assertSame($this->zas->id, $e['project']['id']);
        $this->get("/api/v1/expenses/{$e['id']}/receipt")->assertOk();
        $this->postJson("/api/v1/expenses/{$e['id']}/action", ['action' => 'check'])->assertStatus(422);

        Sanctum::actingAs($this->tech2);
        $this->get("/api/v1/expenses/{$e['id']}/receipt")->assertForbidden();
        $this->getJson('/api/v1/expenses')->assertJsonCount(0, 'data');

        // dispečer vrátí → technik opraví → znovu odesláno
        Sanctum::actingAs($this->dispecer);
        $this->postJson("/api/v1/expenses/{$e['id']}/action", ['action' => 'return'])->assertStatus(422);
        $this->postJson("/api/v1/expenses/{$e['id']}/action", ['action' => 'return', 'note' => 'nečitelná účtenka'])->assertJsonPath('data.status', 'returned');
        Sanctum::actingAs($this->tech1);
        $this->post("/api/v1/expenses/{$e['id']}", ['amount' => 1300, 'receipt' => UploadedFile::fake()->image('nova.jpg')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.amount', 1300);

        Sanctum::actingAs($this->dispecer);
        $this->postJson("/api/v1/expenses/{$e['id']}/action", ['action' => 'check'])->assertJsonPath('data.status', 'checked');
        $this->postJson("/api/v1/expenses/{$e['id']}/action", ['action' => 'approve'])->assertStatus(422);

        Sanctum::actingAs($this->ekonom);
        $this->getJson('/api/v1/expenses?status=checked')->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/expenses/{$e['id']}/action", ['action' => 'approve'])->assertJsonPath('data.status', 'approved');
        $this->postJson("/api/v1/expenses/{$e['id']}/action", ['action' => 'pay'])->assertJsonPath('data.status', 'paid');

        Sanctum::actingAs($this->tech1);
        $this->deleteJson("/api/v1/expenses/{$e['id']}")->assertForbidden();
    }

    public function test_monthly_statement_confirm_pay_and_project_costs(): void
    {
        PayRate::create(['currency' => 'CZK', 'per_visit' => 300, 'per_km' => 4, 'per_hour' => 0]);                       // obecná
        PayRate::create(['user_id' => $this->tech1->id, 'project_id' => $this->zas->id, 'currency' => 'CZK', 'per_visit' => 350, 'per_km' => 5, 'per_hour' => 100]);
        Tariff::create(['project_id' => $this->zas->id, 'service_type' => 'servis', 'currency' => 'CZK', 'base_price' => 450, 'km_rate' => 2.5]);

        // dva hotové výjezdy v listopadu, jeden rozpracovaný (nepočítá se)
        Sanctum::actingAs($this->tech1);
        foreach ([[20, '09:00', '10:30'], [40, '13:00', '13:30'], [10, '15:00', '16:00']] as $i => [$km, $a, $b]) {
            $t = $this->makeTicket($this->locA, $this->tech1);
            $uuid = $this->uuid();
            $this->putJson("/api/v1/tickets/{$t->id}/visits/$uuid", [
                'arrived_at' => "2026-11-1{$i}T$a:00+01:00", 'departed_at' => "2026-11-1{$i}T$b:00+01:00", 'km' => $km, 'work_description' => 'x', 'test_result' => 'ok',
            ])->assertSuccessful();
            if ($i < 2) {
                $this->postJson("/api/v1/visits/$uuid/submit")->assertOk();
            }
        }
        $e = $this->addExpense(['amount' => 500]);
        $company = $this->addExpense(['amount' => 900, 'paid_with' => 'company']);
        Sanctum::actingAs($this->ekonom);
        $this->postJson("/api/v1/expenses/{$e['id']}/action", ['action' => 'approve', 'project_id' => $this->zas->id])->assertOk();
        $this->postJson("/api/v1/expenses/{$company['id']}/action", ['action' => 'approve', 'project_id' => $this->zas->id])->assertOk();

        // technik vidí svůj přehled: 2×350 + 60 km×5 + 2 h×100 + výdaj 500 (firemní karta se neproplácí)
        Sanctum::actingAs($this->tech1);
        $s = $this->getJson('/api/v1/payroll/statement?month=2026-11')->assertOk()->json('data');
        $this->assertCount(2, $s['rows']);
        $this->assertEquals(700 + 300 + 200 + 500, $s['totals'][0]['total']);
        $this->getJson('/api/v1/payroll/statement?month=2026-11&user_id='.$this->tech2->id)->assertForbidden();
        $this->getJson('/api/v1/payroll?month=2026-11')->assertForbidden();

        Sanctum::actingAs($this->ekonom);
        $ov = $this->getJson('/api/v1/payroll?month=2026-11')->assertOk()->json('data');
        $this->assertSame($this->tech1->id, $ov[0]['technician']['id']);
        $pid = $this->postJson('/api/v1/payroll/confirm', ['user_id' => $this->tech1->id, 'month' => '2026-11'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/payroll/confirm', ['user_id' => $this->tech1->id, 'month' => '2026-11'])->assertStatus(422);

        // po potvrzení se čísla nemění, i když se změní sazba
        PayRate::query()->update(['per_visit' => 999]);
        $this->assertEquals(1700, $this->getJson('/api/v1/payroll/statement?month=2026-11&user_id='.$this->tech1->id)->json('data.totals.0.total'));
        $this->postJson("/api/v1/payouts/$pid/pay")->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertSame('paid', Expense::find($e['id'])->status);
        $this->assertStringContainsString('CELKEM', $this->get('/api/v1/payroll/export?month=2026-11')->streamedContent());

        // náklady projektu: příjmy 2× (450 + km×2,5) + rozpracovaná zakázka; práce s aktuální sazbou 999
        $pc = collect($this->getJson('/api/v1/project-costs?month=2026-11')->assertOk()->json('data'))->firstWhere('project_id', $this->zas->id);
        $this->assertEquals(3 * 450 + (20 + 40 + 10) * 2.5, $pc['revenue']);
        $this->assertEquals(1400, $pc['expenses']);
        $this->assertSame(2, $pc['visits']);

        Sanctum::actingAs($this->dispecer);
        $this->getJson('/api/v1/project-costs?month=2026-11')->assertForbidden();
        $this->postJson('/api/v1/pay-rates', ['currency' => 'CZK', 'per_visit' => 1, 'per_km' => 1, 'per_hour' => 1])->assertForbidden();
    }
}
