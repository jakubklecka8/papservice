<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Návod = téma (závada / postup); obsah žije ve verzích, technik vidí jen zveřejněnou
        Schema::create('instructions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_type', 100)->nullable();       // Z-BOX, kiosek…
            $table->string('title');
            $table->string('tags')->nullable();                    // klíčová slova pro hledání
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('instruction_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instruction_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 16)->default('draft');        // draft|published|archived
            $table->text('symptom')->nullable();                    // jak se závada projevuje
            $table->json('steps');                                  // [{text, outcomes:[{if, then}]}]
            $table->text('note')->nullable();                       // poznámka / bezpečnost
            $table->string('change_note')->nullable();              // co se změnilo oproti minulé verzi
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['instruction_id', 'version']);
        });

        // hodnocení technikem: pomohl / nepomohl (+ komentář, k výjezdu)
        Schema::create('instruction_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instruction_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('helpful');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['instruction_version_id', 'user_id', 'visit_id']);
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->foreignId('instruction_version_id')->nullable()->after('instruction_ref')->constrained()->nullOnDelete();
        });

        $this->seedZasilkovna();
    }

    /** První návody Zásilkovny (Z-BOX) – podle podkladů od Jakuba */
    private function seedZasilkovna(): void
    {
        $projectId = DB::table('projects')->where('code', 'ZAS')->value('id');
        if (! $projectId) {
            return;
        }
        $now = now();
        $items = [
            [
                'title' => 'Nejdou otevřít dvířka schránky',
                'tags' => 'dvířka, schránka, zámek, silová deska, neotevře',
                'symptom' => 'Jedna schránka se při vydání/vložení zásilky neotevře, ostatní ve sloupci fungují.',
                'steps' => [
                    ['text' => 'Pořádně bouchnout do dvířek – častá příčina je špatný kontakt na zámku. Zkusit znovu otevřít.', 'outcomes' => [
                        ['if' => 'Dvířka se otevřou', 'then' => 'Hotovo – do protokolu zapsat „uvolněn kontakt zámku“.'],
                    ]],
                    ['text' => 'Prohodit zapojení zámku na silové desce (na jiný, funkční výstup) a zkusit otevřít.', 'outcomes' => [
                        ['if' => 'Dvířka se otevřou', 'then' => 'Vadná silová deska → vyměnit silovou desku.'],
                        ['if' => 'Dvířka se neotevřou', 'then' => 'Vadný zámek → vyměnit zámek.'],
                    ]],
                ],
            ],
            [
                'title' => 'Nejde otevřít celý sloupec',
                'tags' => 'sloupec, všechny schránky, silová deska',
                'symptom' => 'Neotevírá se žádná schránka v jednom sloupci.',
                'steps' => [
                    ['text' => 'Vyměnit silovou desku daného sloupce.', 'outcomes' => []],
                ],
            ],
            [
                'title' => 'Špatná telemetrie / komunikace',
                'tags' => 'telemetrie, komunikace, offline, SIM, modem, základní deska',
                'symptom' => 'Z-BOX je offline nebo hlásí chyby komunikace / telemetrie.',
                'steps' => [
                    ['text' => 'Vyndat SIM kartu, namazat kontakty (kontaktní sprej) a vrátit zpět; případně vložit SIM do adaptéru.', 'outcomes' => [
                        ['if' => 'Komunikace naběhne', 'then' => 'Hotovo – ověřit telemetrii v systému.'],
                    ]],
                    ['text' => 'Pokud ani to nepomůže → vyměnit základní (hlavní) desku.', 'outcomes' => []],
                ],
            ],
        ];
        foreach ($items as $it) {
            $iid = DB::table('instructions')->insertGetId([
                'project_id' => $projectId, 'device_type' => 'Z-BOX', 'title' => $it['title'], 'tags' => $it['tags'],
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $vid = DB::table('instruction_versions')->insertGetId([
                'instruction_id' => $iid, 'version' => 1, 'status' => 'published', 'symptom' => $it['symptom'],
                'steps' => json_encode($it['steps'], JSON_UNESCAPED_UNICODE), 'change_note' => 'První verze',
                'published_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('instructions')->where('id', $iid)->update(['published_version_id' => $vid]);
        }
    }

    public function down(): void
    {
        Schema::table('visits', fn (Blueprint $t) => $t->dropConstrainedForeignId('instruction_version_id'));
        Schema::dropIfExists('instruction_ratings');
        Schema::dropIfExists('instruction_versions');
        Schema::dropIfExists('instructions');
    }
};
