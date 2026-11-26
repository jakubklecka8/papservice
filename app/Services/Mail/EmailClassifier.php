<?php

namespace App\Services\Mail;

use App\Models\InboundEmail;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Vytáhne z e-mailu údaje požadavku. S API klíčem přes Claude (strukturovaný výstup
 * přes nástroj), bez klíče jednoduchou heuristikou. AI jen navrhuje – provozovnu
 * pak dohledá LocationMatcher v databázi a nejasnosti jdou dispečerovi.
 */
class EmailClassifier
{
    public const DEFAULT_MODEL = 'claude-haiku-5-5';

    public function hasAi(): bool
    {
        return filled(Setting::getSecret('anthropic_api_key'));
    }

    /**
     * @return array{is_request: bool, end_customer_code: ?string, service_type: string, priority: string, title: string,
     *               summary: string, language: string, location_hints: array<string, ?string>, confidence: float, reason: string, engine: string}
     */
    public function classify(InboundEmail $email): array
    {
        $project = $email->mailbox->project()->with('endCustomers')->first();
        $types = $project->service_types ?: ['servis'];

        if ($this->hasAi()) {
            try {
                return $this->viaClaude($email, $project, $types);
            } catch (Throwable $e) {
                report($e);
                $fallback = $this->heuristic($email, $types);
                $fallback['reason'] = 'AI nedostupná ('.Str::limit($e->getMessage(), 120).'), použita heuristika.';

                return $fallback;
            }
        }

        return $this->heuristic($email, $types);
    }

    private function heuristic(InboundEmail $email, array $types): array
    {
        $text = mb_strtolower($email->subject.' '.$email->body_text);
        $type = in_array('instalace', $types, true) && preg_match('/instal|montáž|montaz|instalacj/u', $text) ? 'instalace' : $types[0];
        $urgent = (bool) preg_match('/urgent|naléhav|nalehav|pilne|pilny|asap|ihned|okamžitě/u', $text);

        return [
            'is_request' => true,
            'end_customer_code' => null,
            'service_type' => $type,
            'priority' => $urgent ? 'high' : 'normal',
            'title' => Str::limit(trim(preg_replace('/^(re|fw|fwd|odp|aw)\s*:\s*/i', '', (string) $email->subject)) ?: 'Požadavek z e-mailu', 250, ''),
            'summary' => '',
            'language' => preg_match('/[ąęłńśźż]/u', $text) ? 'pl' : 'cs',
            'location_hints' => [],
            'confidence' => 0.0,
            'reason' => 'Bez AI – provozovna se hledá jen podle kódu v textu.',
            'engine' => 'heuristic',
        ];
    }

    private function viaClaude(InboundEmail $email, $project, array $types): array
    {
        $customers = $project->endCustomers->map(fn ($c) => $c->code.' = '.$c->name)->implode('; ');
        $attachments = collect($email->attachments ?? [])->pluck('name')->implode(', ');
        $body = Str::limit((string) $email->body_text, 8000);

        $system = <<<TXT
Jsi asistent servisního dispečinku firmy PAP Service. Z příchozího e-mailu vytáhni údaje servisního požadavku pro projekt „{$project->name}“.
Koncoví zákazníci projektu: {$customers}.
Typy práce: {$this->list($types)}.
Pravidla:
- is_request = false, pokud e-mail není nový požadavek na servis/instalaci: reklama, faktura, newsletter, automatická odpověď, SLA upozornění/připomínka („dosáhlo 50 %“, breach), změna stavu, poděkování bez požadavku.
- location_hints vyplň jen tím, co v e-mailu opravdu je (kód/číslo provozovny, značka, město, ulice, PSČ, název). Nic nevymýšlej.
- title: krátký popis závady/práce (max 80 znaků) v jazyce e-mailu.
- summary: 1–3 věty, co se stalo a co je potřeba, v češtině.
- priority: urgent jen při výslovné naléhavosti nebo nefunkčním zařízení ohrožujícím provoz, jinak normal.
- confidence: jak moc si jsi jistý(á) údaji o provozovně (0–1).
TXT;

        $tool = [
            'name' => 'zapsat_pozadavek',
            'description' => 'Zapíše strukturované údaje servisního požadavku z e-mailu.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'is_request' => ['type' => 'boolean'],
                    'end_customer_code' => ['type' => ['string', 'null'], 'description' => 'kód koncového zákazníka ze seznamu, nebo null'],
                    'service_type' => ['type' => 'string', 'enum' => array_values($types)],
                    'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']],
                    'title' => ['type' => 'string'],
                    'summary' => ['type' => 'string'],
                    'language' => ['type' => 'string', 'enum' => ['cs', 'pl', 'en', 'de', 'sk']],
                    'location_hints' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => ['type' => ['string', 'null']],
                            'brand' => ['type' => ['string', 'null']],
                            'store_name' => ['type' => ['string', 'null']],
                            'city' => ['type' => ['string', 'null']],
                            'street' => ['type' => ['string', 'null']],
                            'zip' => ['type' => ['string', 'null']],
                        ],
                    ],
                    'confidence' => ['type' => 'number'],
                    'reason' => ['type' => 'string', 'description' => 'krátké zdůvodnění v češtině'],
                ],
                'required' => ['is_request', 'service_type', 'priority', 'title', 'summary', 'language', 'location_hints', 'confidence', 'reason'],
            ],
        ];

        $res = Http::timeout(60)->retry(2, 2000, throw: false)
            ->withHeaders([
                'x-api-key' => Setting::getSecret('anthropic_api_key'),
                'anthropic-version' => '2023-06-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => Setting::get('anthropic_model') ?: self::DEFAULT_MODEL,
                'max_tokens' => 1024,
                'system' => $system,
                'tools' => [$tool],
                'tool_choice' => ['type' => 'tool', 'name' => 'zapsat_pozadavek'],
                'messages' => [[
                    'role' => 'user',
                    'content' => "Od: {$email->from_name} <{$email->from_email}>\nPředmět: {$email->subject}\nPřílohy: ".($attachments ?: '—')."\n\n{$body}",
                ]],
            ]);

        if (! $res->successful()) {
            throw new \RuntimeException('Claude API '.$res->status().': '.Str::limit($res->body(), 200));
        }

        $input = collect($res->json('content'))->firstWhere('type', 'tool_use')['input'] ?? null;
        if (! is_array($input)) {
            throw new \RuntimeException('Claude API nevrátil strukturovaný výsledek.');
        }

        return [
            'is_request' => (bool) ($input['is_request'] ?? true),
            'end_customer_code' => $input['end_customer_code'] ?? null,
            'service_type' => in_array($input['service_type'] ?? '', $types, true) ? $input['service_type'] : $types[0],
            'priority' => in_array($input['priority'] ?? '', ['low', 'normal', 'high', 'urgent'], true) ? $input['priority'] : 'normal',
            'title' => Str::limit(trim((string) ($input['title'] ?? $email->subject ?? 'Požadavek z e-mailu')), 250, ''),
            'summary' => (string) ($input['summary'] ?? ''),
            'language' => (string) ($input['language'] ?? 'cs'),
            'location_hints' => array_filter((array) ($input['location_hints'] ?? []), fn ($v) => filled($v)),
            'confidence' => max(0, min(1, (float) ($input['confidence'] ?? 0))),
            'reason' => (string) ($input['reason'] ?? ''),
            'engine' => 'claude',
        ];
    }

    private function list(array $v): string
    {
        return implode(', ', $v);
    }
}
