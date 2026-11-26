<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Instruction;
use App\Models\Part;
use App\Models\Setting;
use App\Models\Ticket;
use App\Services\Mail\EmailClassifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * AI doporučení k závadě: z popisu zakázky, historie zařízení a provozovny,
 * podobných vyřešených zakázek a zveřejněných návodů navrhne příčinu, postup
 * a díly s sebou. Výsledek se uloží k zakázce (tickets.ai_advice).
 */
class TicketAdvisor
{
    public function available(): bool
    {
        return filled(Setting::getSecret('anthropic_api_key'));
    }

    /** @return array<string, mixed> */
    public function advise(Ticket $ticket, string $lang = 'cs'): array
    {
        abort_unless($this->available(), 422, 'AI není nastavená – vlož API klíč v Nastavení → AI asistent.');
        $ctx = $this->context($ticket);

        $system = <<<TXT
Jsi zkušený servisní technik a vedoucí servisu firmy PAP Service (servis výdejních boxů Z-BOX, paczkomatů, kiosků a pokladen pro Zásilkovnu, Allegro a M4B/KFC).
Dostaneš novou zakázku a podklady z historie. Navrhni dispečerovi a technikovi, co udělat.
Pravidla:
- Opírej se hlavně o PODKLADY: zveřejněné návody firmy a to, co skutečně pomohlo u podobných zakázek. Obecné znalosti použij jen jako doplněk a řekni to.
- Když existuje návod, který sedí, uveď jeho id v instruction_ids a postup s ním sjednoť.
- Postup piš jako krátké praktické kroky pro technika na místě, nejdřív to nejrychlejší a nejlevnější.
- Díly navrhuj jen ty, které dávají smysl vzít s sebou; když znáš katalogové číslo z katalogu, uveď ho.
- Když se závada na stejné provozovně/zařízení opakuje, upozorni na to (repeat_warning).
- Když z popisu nejde poznat, co se děje, napiš do questions, na co se zeptat zákazníka.
- Nevymýšlej si čísla zakázek ani id, používej jen ta z podkladů.
- Odpovídej jazykem: {$lang} (cs = česky, pl = polsky). Stručně.
TXT;

        $tool = [
            'name' => 'doporuceni',
            'description' => 'Doporučení k závadě',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'summary' => ['type' => 'string', 'description' => '1–2 věty: co se nejspíš děje a co udělat'],
                    'causes' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'cause' => ['type' => 'string'],
                        'likelihood' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                        'why' => ['type' => 'string'],
                    ], 'required' => ['cause', 'likelihood']]],
                    'steps' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'parts' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'name' => ['type' => 'string'],
                        'part_number' => ['type' => ['string', 'null']],
                        'why' => ['type' => 'string'],
                    ], 'required' => ['name']]],
                    'instruction_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'similar_ticket_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'id podobných zakázek z podkladů, které pomohly'],
                    'repeat_warning' => ['type' => ['string', 'null']],
                    'questions' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'based_on' => ['type' => 'string', 'enum' => ['instructions', 'history', 'general', 'mixed']],
                    'confidence' => ['type' => 'number', 'description' => '0–1'],
                ],
                'required' => ['summary', 'causes', 'steps', 'parts', 'instruction_ids', 'based_on', 'confidence'],
            ],
        ];

        $res = Http::timeout(55)->retry(1, 1500, throw: false)
            ->withHeaders(['x-api-key' => Setting::getSecret('anthropic_api_key'), 'anthropic-version' => '2023-06-01'])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => Setting::get('anthropic_model') ?: EmailClassifier::DEFAULT_MODEL,
                'max_tokens' => 1500,
                'system' => $system,
                'tools' => [$tool],
                'tool_choice' => ['type' => 'tool', 'name' => 'doporuceni'],
                'messages' => [['role' => 'user', 'content' => $ctx['text']]],
            ]);
        if (! $res->successful()) {
            abort(502, 'AI neodpověděla ('.$res->status().'): '.Str::limit((string) $res->json('error.message', $res->body()), 200));
        }
        $in = collect($res->json('content'))->firstWhere('type', 'tool_use')['input'] ?? null;
        abort_unless(is_array($in), 502, 'AI nevrátila doporučení.');

        // jen id, která AI opravdu dostala
        $instrIds = array_values(array_intersect(array_map('intval', $in['instruction_ids'] ?? []), $ctx['instruction_ids']));
        $simIds = array_values(array_intersect(array_map('intval', $in['similar_ticket_ids'] ?? []), $ctx['ticket_ids']));
        $instr = Instruction::with('published:id,version')->whereIn('id', $instrIds)->get(['id', 'title', 'published_version_id']);
        $sim = Ticket::whereIn('id', $simIds)->get(['id', 'number', 'title']);

        $advice = [
            'summary' => (string) ($in['summary'] ?? ''),
            'causes' => array_values(array_map(fn ($c) => [
                'cause' => (string) ($c['cause'] ?? ''), 'likelihood' => in_array($c['likelihood'] ?? '', ['high', 'medium', 'low'], true) ? $c['likelihood'] : 'medium',
                'why' => $c['why'] ?? null,
            ], array_slice((array) ($in['causes'] ?? []), 0, 5))),
            'steps' => array_values(array_map('strval', array_slice((array) ($in['steps'] ?? []), 0, 12))),
            'parts' => array_values(array_map(fn ($p) => ['name' => (string) ($p['name'] ?? ''), 'part_number' => $p['part_number'] ?? null, 'why' => $p['why'] ?? null],
                array_slice((array) ($in['parts'] ?? []), 0, 8))),
            'instructions' => $instr->map(fn ($i) => ['id' => $i->id, 'title' => $i->title, 'version' => $i->published?->version])->values()->all(),
            'similar' => $sim->map(fn ($t) => ['id' => $t->id, 'number' => $t->number, 'title' => $t->title])->values()->all(),
            'repeat_warning' => $in['repeat_warning'] ?? null,
            'questions' => array_values(array_map('strval', array_slice((array) ($in['questions'] ?? []), 0, 5))),
            'based_on' => $in['based_on'] ?? 'mixed',
            'confidence' => max(0, min(1, (float) ($in['confidence'] ?? 0.5))),
            'lang' => $lang,
            'stats' => $ctx['stats'],
        ];
        $ticket->forceFill(['ai_advice' => $advice, 'ai_advice_at' => now()])->saveQuietly();

        return $advice;
    }

    /** @return array{text: string, instruction_ids: list<int>, ticket_ids: list<int>, stats: array<string, int>} */
    public function context(Ticket $ticket): array
    {
        $ticket->loadMissing(['project', 'location', 'device.events']);
        $L = [];
        $L[] = '## NOVÁ ZAKÁZKA';
        $L[] = 'Projekt: '.$ticket->project?->name.' · typ: '.$ticket->service_type.' · priorita: '.$ticket->priority;
        if ($ticket->location) {
            $L[] = 'Provozovna: '.$ticket->location->code.' – '.$ticket->location->name.', '.$ticket->location->city;
        }
        $L[] = 'Název: '.$ticket->title;
        $L[] = 'Popis: '.Str::limit((string) $ticket->description, 2500);

        if ($d = $ticket->device) {
            $L[] = "\n## ZAŘÍZENÍ\n{$d->type} {$d->model} · S/N {$d->serial_number} · instalace ".($d->installed_at?->format('d.m.Y') ?? '?');
            foreach ($d->events->take(10) as $e) {
                $L[] = '- '.$e->happened_at?->format('d.m.Y').' '.$e->type.': '.Str::limit(str_replace("\n", ' ', (string) $e->note), 200);
            }
        }

        // historie na stejné provozovně
        $ticketIds = [];
        $history = $ticket->location_id ? $this->solved(Ticket::where('location_id', $ticket->location_id)->where('id', '!=', $ticket->id), 10) : collect();
        if ($history->isNotEmpty()) {
            $L[] = "\n## HISTORIE NA TÉTO PROVOZOVNĚ (nejnovější první)";
            foreach ($history as $t) {
                $L[] = $this->line($t);
                $ticketIds[] = $t->id;
            }
        }

        // podobné vyřešené zakázky v projektu (shoda slov v názvu/popisu)
        $words = $this->keywords($ticket->title.' '.$ticket->description);
        $similar = collect();
        if ($words) {
            $cands = Ticket::where('project_id', $ticket->project_id)->where('id', '!=', $ticket->id)
                ->whereNotIn('id', $ticketIds)
                ->whereIn('status', [TicketStatus::Completed->value, TicketStatus::Closed->value])
                ->where(fn ($q) => collect($words)->each(fn ($w) => $q->orWhere('title', 'like', '%'.$w.'%')))
                ->latest('id')->limit(120)->get(['id', 'title', 'description']);
            $similar = $cands->map(fn ($t) => [$t, count(array_intersect($words, $this->keywords($t->title.' '.$t->description)))])
                ->sortByDesc(fn ($x) => $x[1])->take(12)->pluck(0);
            $similar = $this->solved(Ticket::whereIn('id', $similar->pluck('id')), 12);
        }
        if ($similar->isNotEmpty()) {
            $L[] = "\n## PODOBNÉ VYŘEŠENÉ ZAKÁZKY (co pomohlo)";
            foreach ($similar as $t) {
                $L[] = $this->line($t);
                $ticketIds[] = $t->id;
            }
        }

        // zveřejněné návody projektu
        $instr = Instruction::with('published')->where('is_active', true)->whereNotNull('published_version_id')
            ->where(fn ($q) => $q->where('project_id', $ticket->project_id)->orWhereNull('project_id'))->limit(60)->get();
        if ($instr->isNotEmpty()) {
            $L[] = "\n## ZVEŘEJNĚNÉ NÁVODY FIRMY";
            foreach ($instr as $i) {
                $steps = collect($i->published->steps ?? [])->map(fn ($s, $n) => ($n + 1).'. '.$s['text'].collect($s['outcomes'] ?? [])->map(fn ($o) => ' [když '.$o['if'].' → '.$o['then'].']')->implode(''))->implode(' ');
                $L[] = "[návod id={$i->id}] {$i->title} ({$i->device_type}) – projev: {$i->published->symptom} – postup: {$steps}";
            }
        }

        // katalog dílů (aby AI uměla uvést katalogové číslo)
        $parts = Part::query()->where('is_active', true)->orderBy('name')->limit(150)->get(['part_number', 'name']);
        if ($parts->isNotEmpty()) {
            $L[] = "\n## KATALOG DÍLŮ\n".$parts->map(fn ($p) => $p->part_number.' '.$p->name)->implode('; ');
        }

        return [
            'text' => implode("\n", $L),
            'instruction_ids' => $instr->pluck('id')->all(),
            'ticket_ids' => array_values(array_unique($ticketIds)),
            'stats' => ['history' => $history->count(), 'similar' => $similar->count(), 'instructions' => $instr->count()],
        ];
    }

    /** Zakázky s odeslanou návštěvou (máme co pomohlo) @return Collection<int, Ticket> */
    private function solved($query, int $limit): Collection
    {
        return $query->with(['visits' => fn ($v) => $v->whereIn('status', ['submitted', 'approved'])->with('parts:id,visit_id,name,part_number')])
            ->whereHas('visits', fn ($v) => $v->whereIn('status', ['submitted', 'approved']))
            ->latest('id')->limit($limit)->get();
    }

    private function line(Ticket $t): string
    {
        $v = $t->visits->last();
        $parts = $v?->parts->map(fn ($p) => $p->name)->implode(', ');

        return "- [zakázka id={$t->id} {$t->number} ".$t->created_at?->format('d.m.Y').'] '.Str::limit($t->title, 120)
            .($v ? ' → PRÁCE: '.Str::limit(str_replace("\n", ' ', (string) $v->work_description), 300)
                .($v->repair_description ? ' · PŘÍČINA: '.Str::limit(str_replace("\n", ' ', $v->repair_description), 200) : '')
                .($parts ? ' · DÍLY: '.$parts : '').' · TEST: '.($v->test_result?->value ?? '?') : '');
    }

    /** @return list<string> */
    private function keywords(string $text): array
    {
        $stop = ['nejde', 'nefunguje', 'prosím', 'prosim', 'dobrý', 'dobry', 'den', 'zdravím', 'servis', 'oprava', 'please', 'proszę', 'nie', 'działa', 'dzień', 'dobry', 'pro', 'the', 'and', 'jak', 'jest', 'bylo', 'bude', 'jsou'];
        $w = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(Str::limit($text, 600, '')), -1, PREG_SPLIT_NO_EMPTY);
        $w = array_filter($w, fn ($x) => mb_strlen($x) >= 4 && ! in_array($x, $stop, true));

        // kmen slova (první 5 znaků) – zachytí skloňování (schránka/schránky, dvířka/dvířek)
        return array_values(array_unique(array_map(fn ($x) => mb_substr($x, 0, 5), $w)));
    }
}
