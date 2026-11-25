<?php

namespace App\Services;

use App\Models\Visit;

/**
 * Sestaví data servisního protokolu ze záznamu návštěvy.
 * Výsledek se ukládá jako snímek do protocols.data – protokol tak vždy
 * odpovídá stavu zakázky v okamžiku schválení (2.2).
 */
class ProtocolBuilder
{
    public const LABELS = [
        'cs' => [
            'title' => 'Servisní protokol',
            'number' => 'Číslo zakázky',
            'project' => 'Projekt',
            'location' => 'Provozovna',
            'address' => 'Adresa',
            'technician' => 'Technik',
            'arrived' => 'Příjezd',
            'departed' => 'Odjezd',
            'minutes' => 'Čas na místě (min)',
            'km' => 'Kilometry',
            'reported' => 'Nahlášená závada',
            'work' => 'Provedené práce',
            'repair' => 'Popis opravy / příčina',
            'test' => 'Výsledek testu',
            'test_ok' => 'V pořádku',
            'test_failed' => 'Nevyhověl',
            'test_na' => 'Neprováděl se',
            'instruction' => 'Použitý návod',
            'parts' => 'Použité díly',
            'part' => 'Díl',
            'qty' => 'Množství',
            'serial_removed' => 'S/N demontováno',
            'serial_installed' => 'S/N namontováno',
            'missing' => 'chybí',
            'photos' => 'Počet fotografií',
            'approved' => 'Schváleno',
        ],
        'pl' => [
            'title' => 'Protokół serwisowy',
            'number' => 'Numer zlecenia',
            'project' => 'Projekt',
            'location' => 'Lokalizacja',
            'address' => 'Adres',
            'technician' => 'Technik',
            'arrived' => 'Przyjazd',
            'departed' => 'Wyjazd',
            'minutes' => 'Czas na miejscu (min)',
            'km' => 'Kilometry',
            'reported' => 'Zgłoszona usterka',
            'work' => 'Wykonane prace',
            'repair' => 'Opis naprawy / przyczyna',
            'test' => 'Wynik testu',
            'test_ok' => 'Poprawny',
            'test_failed' => 'Negatywny',
            'test_na' => 'Nie wykonano',
            'instruction' => 'Użyta instrukcja',
            'parts' => 'Użyte części',
            'part' => 'Część',
            'qty' => 'Ilość',
            'serial_removed' => 'S/N zdemontowany',
            'serial_installed' => 'S/N zamontowany',
            'missing' => 'brak',
            'photos' => 'Liczba zdjęć',
            'approved' => 'Zatwierdzono',
        ],
    ];

    /** @return array<string, mixed> */
    public function build(Visit $visit, string $language): array
    {
        $visit->loadMissing(['ticket.project', 'ticket.location', 'technician', 'parts.part', 'attachments']);
        $ticket = $visit->ticket;
        $location = $ticket->location;

        return [
            'language' => $language,
            'ticket' => [
                'number' => $ticket->number,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'service_type' => $ticket->service_type,
            ],
            'project' => ['code' => $ticket->project->code, 'name' => $ticket->project->name],
            'location' => $location ? [
                'code' => $location->code,
                'name' => $location->name,
                'address' => trim(implode(', ', array_filter([$location->street, trim($location->zip.' '.$location->city), $location->country]))),
            ] : null,
            'technician' => $visit->technician->name,
            'visit' => [
                'uuid' => $visit->uuid,
                'arrived_at' => $visit->arrived_at?->toIso8601String(),
                'departed_at' => $visit->departed_at?->toIso8601String(),
                'minutes_on_site' => $visit->minutes_on_site,
                'km' => $visit->km,
                'work_description' => $visit->work_description,
                'repair_description' => $visit->repair_description,
                'test_result' => $visit->test_result?->value,
                'test_note' => $visit->test_note,
                'instruction_ref' => $visit->instruction_ref,
            ],
            'parts' => $visit->parts->sortBy('id')->values()->map(fn ($p) => [
                'name' => $p->name,
                'part_number' => $p->part_number,
                'quantity' => $p->quantity,
                'serial_removed' => $p->serial_removed,
                'serial_installed' => $p->serial_installed,
                'serialized' => (bool) $p->part?->serialized,
            ])->all(),
            'photos' => $visit->attachments->where('kind', 'photo')->sortBy('id')->pluck('uuid')->values()->all(),
            'labels' => self::LABELS[$language],
        ];
    }
}
