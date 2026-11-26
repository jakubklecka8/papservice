<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PapFixtures;
use Tests\TestCase;

/** Krok 10 – návody: verze, zveřejnění, vyhledávání, hodnocení, vazba na výjezd */
class InstructionTest extends TestCase
{
    use PapFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedPap();
    }

    public function test_versions_publish_search_rate_and_visit(): void
    {
        Sanctum::actingAs($this->dispecer);
        $id = $this->postJson('/api/v1/instructions', [
            'title' => 'Nejdou otevřít dvířka schránky', 'project_id' => $this->zas->id, 'device_type' => 'Z-BOX', 'tags' => 'zámek, silová deska',
            'symptom' => 'Jedna schránka se neotevře',
            'steps' => [['text' => 'Bouchnout do dvířek'], ['text' => 'Prohodit zapojení na silové desce', 'outcomes' => [
                ['if' => 'Otevřou se', 'then' => 'Vadná silová deska'], ['if' => 'Neotevřou', 'then' => 'Vadný zámek'],
            ]]],
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/instructions', ['title' => 'X', 'project_id' => $this->alg->id, 'steps' => [['text' => 'a']]])->assertForbidden();

        // koncept technik nevidí
        Sanctum::actingAs($this->tech1);
        $this->getJson('/api/v1/instructions')->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/instructions/$id")->assertNotFound();

        Sanctum::actingAs($this->dispecer);
        $v1 = $this->getJson("/api/v1/instructions/$id")->json('data.versions.0.id');
        $this->postJson("/api/v1/instruction-versions/$v1/publish")->assertOk()->assertJsonPath('data.status', 'published');

        Sanctum::actingAs($this->tech1);
        $this->getJson('/api/v1/instructions?q=silová')->assertJsonCount(1, 'data')->assertJsonPath('data.0.version', 1);
        $this->getJson('/api/v1/instructions?q=deska zámek')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/instructions?q=telemetrie')->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/instructions/$id")->assertOk()->assertJsonCount(1, 'data.versions')
            ->assertJsonPath('data.versions.0.steps.1.outcomes.1.then', 'Vadný zámek');

        // výjezd s návodem → název do protokolu, hodnocení
        $ticket = $this->makeTicket($this->locA, $this->tech1);
        $uuid = $this->uuid();
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$uuid", ['instruction_version_id' => $v1])->assertSuccessful()
            ->assertJsonPath('data.instruction_ref', 'Nejdou otevřít dvířka schránky (v1)');
        $this->postJson("/api/v1/instruction-versions/$v1/rate", ['helpful' => false, 'comment' => 'Chybí kontrola pojistky', 'visit_uuid' => $uuid])->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.helpful', 0)->assertJsonPath('data.used', 1);
        $this->postJson("/api/v1/instructions/$id/draft", ['steps' => [['text' => 'x']]])->assertForbidden();

        // dispečer připraví v2 → v1 se archivuje, technik vidí v2
        Sanctum::actingAs($this->dispecer);
        $this->getJson("/api/v1/instructions/$id")->assertJsonPath('data.comments.0.comment', 'Chybí kontrola pojistky');
        $this->postJson("/api/v1/instructions/$id/draft", ['symptom' => 'Jedna schránka', 'change_note' => 'Přidána pojistka',
            'steps' => [['text' => 'Zkontrolovat pojistku'], ['text' => 'Bouchnout do dvířek']]])->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.status', 'draft');
        $this->postJson("/api/v1/instructions/$id/draft", ['steps' => [['text' => 'Zkontrolovat pojistku F2'], ['text' => 'Bouchnout']], 'publish' => true])
            ->assertJsonPath('data.version', 2)->assertJsonPath('data.status', 'published');
        $vers = $this->getJson("/api/v1/instructions/$id")->json('data.versions');
        $this->assertSame(['published', 'archived'], array_column($vers, 'status'));

        Sanctum::actingAs($this->tech1);
        $this->getJson("/api/v1/instructions/$id")->assertJsonPath('data.versions.0.version', 2)->assertJsonPath('data.versions.0.steps.0.text', 'Zkontrolovat pojistku F2');
    }
}
