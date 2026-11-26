<?php

namespace App\Services\Mail;

/**
 * Notifikace Zásilkovny – pevný formát, čte se bez AI.
 * Převzato ze staré aplikace (functions.php → parseServiceNotification + email_checker.php → parseRitmBody):
 *
 * Formát 1 (ServiceNow, anglický):
 *   Service type: Závada otevírání schránky  Locker: CZ-Y3VR ID: 11353  Location: Jeseník… (GPS: 50.2 17.2)
 *   Priority: Priority: 3 - Moderate  Description: …  Click here / Ref:
 *
 * Formát 2 (starý český HTML):
 *   Z-BOX: CZ-DR14, ID: 7149, Suchdol nad Odrou, Komenského, 74201
 *   Servisní akce: …  Zásilka: …  Doplňující komentář: …  GPS: 49.6 17.9
 */
class RitmParser
{
    public function ritm(?string $subject): ?string
    {
        return $subject && preg_match('/RITM(\d+)/i', $subject, $m) ? 'RITM'.$m[1] : null;
    }

    /**
     * @return array{service_type: string, locker_code: string, locker_id: string, location: string,
     *               gps_lat: ?float, gps_lon: ?float, priority_raw: string, priority: string, description: string}|null
     */
    public function parse(string $raw): ?array
    {
        $text = $this->normalize($raw);

        return $this->format1($text) ?? $this->format2($text);
    }

    /** Stejně jako stará aplikace: QP, <br>, HTML, entity → jeden řádek s mezerami */
    private function normalize(string $text): string
    {
        if (preg_match('/=[0-9A-F]{2}/', $text) && ! preg_match('/[^\x00-\x7F]/', $text)) {
            $text = quoted_printable_decode($text);
        }
        $text = preg_replace('/<br\s*\/?>/i', ' ', $text);
        $text = preg_replace('/<\/(p|div|tr|li|td)>/i', ' ', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\xc2\xa0", "\r\n", "\r", "\n", "\t"], ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private function format1(string $text): ?array
    {
        if (! preg_match('/Service type:\s*(.+?)\s*Locker:/isu', $text, $s)
            || ! preg_match('/Locker:\s*([A-Z]{2}-[A-Z0-9]+).*?ID:?\s*(\d{3,7})/isu', $text, $l)) {
            return null;
        }

        $d = $this->base(trim($s[1]), $l[1], $l[2]);
        if (preg_match('/Location:\s*(.+?)\s*(?:\(GPS:|Priority:|Description:|$)/iu', $text, $m)) {
            $d['location'] = trim($m[1], ' ,;');
        }
        if (preg_match('/GPS:\s*(-?[\d.]+)[,\s]+(-?[\d.]+)/i', $text, $g)) {
            [$d['gps_lat'], $d['gps_lon']] = [(float) $g[1], (float) $g[2]];
        }
        if (preg_match('/Priority:\s*(?:Priority:\s*)?(.+?)\s*(?:Description:|Click here|Ref:|$)/iu', $text, $p)) {
            $d['priority_raw'] = trim($p[1]);
            $d['priority'] = $this->mapPriority($d['priority_raw']);
        }
        if (preg_match('/Description:\s*(.+?)\s*(?:Click here|Ref:|$)/isu', $text, $m)) {
            $d['description'] = trim($m[1]);
        }

        return $d;
    }

    private function format2(string $text): ?array
    {
        if (! preg_match('/Z-BOX:\s*([A-Z]{2}-[A-Z0-9]+),\s*ID:\s*(\d+),\s*([^,]+),\s*([^,]+),\s*(\d{3}\s?\d{2})/iu', $text, $m)) {
            return null;
        }

        $type = preg_match('/Servisní akce:\s*(.+?)\s*(?:Zásilka:|Doplňující komentář:|GPS:|$)/iu', $text, $s) ? trim($s[1]) : 'Servis';
        $d = $this->base($type, $m[1], $m[2]);
        $d['location'] = trim($m[3]).', '.trim($m[4]).', '.trim($m[5]);

        $desc = [];
        if (preg_match('/Zásilka:\s*(.+?)\s*(?:Doplňující komentář:|Servisní akce:|GPS:|$)/iu', $text, $z) && trim($z[1]) !== '') {
            $desc[] = 'Zásilka: '.trim($z[1]);
        }
        if (preg_match('/Doplňující komentář:\s*(.+?)\s*(?:Díky|PRAVIDLA|PRAVDILA|GPS:|$)/isu', $text, $c) && trim($c[1]) !== '') {
            $desc[] = trim($c[1]);
        }
        $d['description'] = implode("\n", $desc);
        if (preg_match('/GPS:\s*(-?[\d.]+)[,\s]+(-?[\d.]+)/i', $text, $g)) {
            [$d['gps_lat'], $d['gps_lon']] = [(float) $g[1], (float) $g[2]];
        }

        return $d;
    }

    private function base(string $type, string $code, string $id): array
    {
        return [
            'service_type' => $type,
            'locker_code' => strtoupper(trim($code)),
            'locker_id' => trim($id),
            'location' => '',
            'gps_lat' => null,
            'gps_lon' => null,
            'priority_raw' => '',
            'priority' => 'normal',
            'description' => '',
        ];
    }

    /** „3 - Moderate“, „1 - Critical“, „High“, „Vysoká“… → naše priority */
    public function mapPriority(string $raw): string
    {
        $r = mb_strtolower($raw);

        return match (true) {
            (bool) preg_match('/^\s*p?1\b|critical|kritick|urgent|nalé?hav/u', $r) => 'urgent',
            (bool) preg_match('/^\s*p?2\b|high|vysok/u', $r) => 'high',
            (bool) preg_match('/^\s*p?[45]\b|low|planning|nízk|nizk/u', $r) => 'low',
            default => 'normal',
        };
    }

    /** „Ostrava, Nádražní 196, 702 00“ / „Suchdol nad Odrou, Komenského, 74201“ → město, ulice, PSČ (Zásilkovna píše město první) */
    public function splitAddress(string $location): array
    {
        $zip = preg_match('/\b(\d{3}\s?\d{2})\b/u', $location, $z) ? $z[1] : null;
        $parts = array_values(array_filter(array_map(
            fn ($p) => trim(preg_replace('/\b\d{3}\s?\d{2}\b/u', '', $p)),
            explode(',', $location),
        ), fn ($p) => $p !== ''));

        return [
            'city' => $parts[0] ?? null,
            'street' => isset($parts[1]) ? implode(', ', array_slice($parts, 1)) : null,
            'zip' => $zip,
        ];
    }
}
