<?php

namespace Tests\Feature;

use App\Models\Instruction;
use App\Models\Setting;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PapFixtures;
use Tests\TestCase;

/** AI doporučení k závadě */
class TicketAdviceTest extends TestCase
{
    use PapFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedPap();
    }

    public function test_advice_uses_history_and_instructions(): void
    {
        $instr = Instruction::create(['project_id' => $this->zas->id, 'device_type' => 'Z-BOX', 'title' => 'Nejdou otevřít dvířka schránky', 'is_active' => true]);
        $v = $instr->versions()->create(['version' => 1, 'status' => 'published', 'symptom' => 'Schránka se neotevře',
            'steps' => [['text' => 'Bouchnout do dvířek', 'outcomes' => []], ['text' => 'Prohodit zapojení na silové desce', 'outcomes' => [['if' => 'otevřou', 'then' => 'vadná deska']]]]]);
        $instr->update(['published_version_id' => $v->id]);

        // dřívější vyřešená zakázka na stejné provozovně
        $old = $this->makeTicket($this->locA, $this->tech1);
        Sanctum::actingAs($this->tech1);
        $uuid = $this->uuid();
        $this->putJson("/api/v1/tickets/{$old->id}/visits/$uuid", ['arrived_at' => '2026-10-01T09:00:00+02:00', 'km' => 5,
            'work_description' => 'Vyměněn zámek schránky 7', 'repair_description' => 'Vadný zámek', 'test_result' => 'ok'])->assertSuccessful();
        $this->postJson("/api/v1/visits/$uuid/submit")->assertOk();
        $old->update(['title' => 'Nejde otevřít schránka 7']);

        $ticket = $this->makeTicket($this->locA, $this->tech1);
        $ticket->update(['title' => 'Nejde otevřít schránka 12', 'description' => 'Zákazník nemůže vyzvednout zásilku, dvířka schránky 12 se neotevřou.']);

        // bez klíče → srozumitelná chyba
        $this->postJson("/api/v1/tickets/{$ticket->id}/advice")->assertStatus(422);

        Setting::putSecret('anthropic_api_key', 'sk-test');
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'doporuceni', 'input' => [
            'summary' => 'Nejspíš špatný kontakt nebo vadný zámek.',
            'causes' => [['cause' => 'Vadný zámek', 'likelihood' => 'high', 'why' => 'Na provozovně se už měnil zámek']],
            'steps' => ['Bouchnout do dvířek', 'Prohodit zapojení na silové desce'],
            'parts' => [['name' => 'Zámek schránky', 'part_number' => 'ZL-100', 'why' => 'pro jistotu']],
            'instruction_ids' => [$instr->id, 99999],
            'similar_ticket_ids' => [$old->id, 88888],
            'repeat_warning' => 'Druhá porucha zámku na provozovně za 2 měsíce.',
            'based_on' => 'mixed', 'confidence' => 0.8,
        ]]]])]);

        $a = $this->postJson("/api/v1/tickets/{$ticket->id}/advice")->assertOk()->json('data');
        $this->assertSame([$instr->id], array_column($a['instructions'], 'id'));
        $this->assertSame([$old->id], array_column($a['similar'], 'id'));
        $this->assertSame(1, $a['stats']['history']);
        $this->assertSame('ZL-100', $a['parts'][0]['part_number']);

        Http::assertSent(function ($req) use ($old) {
            $text = $req['messages'][0]['content'];

            return str_contains($text, 'Vyměněn zámek schránky 7') && str_contains($text, 'Prohodit zapojení na silové desce')
                && str_contains($text, "id={$old->id}") && $req['tool_choice']['name'] === 'doporuceni';
        });
        $this->getJson("/api/v1/tickets/{$ticket->id}/advice")->assertJsonPath('data.summary', 'Nejspíš špatný kontakt nebo vadný zámek.');
        $this->assertNotNull(Ticket::find($ticket->id)->ai_advice_at);

        Sanctum::actingAs($this->customerA);
        $this->getJson("/api/v1/tickets/{$ticket->id}/advice")->assertForbidden();
        Sanctum::actingAs($this->tech2);
        $this->getJson("/api/v1/tickets/{$ticket->id}/advice")->assertForbidden();
    }
}
