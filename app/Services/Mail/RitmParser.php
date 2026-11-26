<?php

namespace App\Services\Mail;

/**
 * Notifikace Zásilkovny (ServiceNow RITM) – pevný formát, čte se bez AI.
 * Převzato ze staré aplikace (email_checker.php → parseRitmBody):
 *   Service type: …
 *   Locker: CZ-XXX ID: 123
 *   Location: … (GPS: 49.8 18.2)
 *   Priority: …
 *   Description: …
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
    public function parse(string $text): ?array
    {
        $text = str_replace(["\xC2\xA0", "\r"], [' ', ''], $text);

        if (! preg_match('/Service type:\s*([^\n]+)/i', $text, $t)) {
            return null;
        }
        if (! preg_match('/Locker:\s*([A-Z]{2}-[A-Z0-9]+)\s*ID:\s*(\d+)/i', $text, $l)) {
            return null;
        }

        $data = [
            'service_type' => trim($t[1]),
            'locker_code' => strtoupper($l[1]),
            'locker_id' => $l[2],
            'location' => '',
            'gps_lat' => null,
            'gps_lon' => null,
            'priority_raw' => '',
            'priority' => 'normal',
            'description' => '',
        ];
        if (preg_match('/Location:\s*(.+?)(?=\(GPS:|\n|$)/i', $text, $loc)) {
            $data['location'] = trim($loc[1], " \t,;");
        }
        if (preg_match('/GPS:\s*(-?[\d.]+)[,\s]+(-?[\d.]+)/i', $text, $g)) {
            $data['gps_lat'] = (float) $g[1];
            $data['gps_lon'] = (float) $g[2];
        }
        if (preg_match('/Priority:\s*(?:Priority:\s*)?([^\n]+)/i', $text, $p)) {
            $data['priority_raw'] = trim($p[1]);
            $data['priority'] = $this->mapPriority($data['priority_raw']);
        }
        if (preg_match('/Description:\s*(.+?)(?=\n\s*\n|Click here|Ref:|$)/is', $text, $d)) {
            $data['description'] = trim($d[1]);
        }

        return $data;
    }

    /** „1 - Critical“, „P2 High“, „Vysoká“… → naše priority */
    public function mapPriority(string $raw): string
    {
        $r = mb_strtolower($raw);

        return match (true) {
            (bool) preg_match('/\b(p?1)\b|critical|kritick|urgent|nalé?hav/u', $r) => 'urgent',
            (bool) preg_match('/\b(p?2)\b|high|vysok/u', $r) => 'high',
            (bool) preg_match('/\b(p?[45])\b|low|nízk|nizk/u', $r) => 'low',
            default => 'normal',
        };
    }

    /** „Ostrava, Nádražní 196, 702 00“ → [street, city, zip] co nejlépe */
    public function splitAddress(string $location): array
    {
        $zip = null;
        if (preg_match('/\b(\d{3}\s?\d{2})\b/u', $location, $z)) {
            $zip = $z[1];
        }
        $parts = array_values(array_filter(array_map('trim', explode(',', $location))));
        $street = null;
        $city = null;
        foreach ($parts as $part) {
            $clean = trim(preg_replace('/\b\d{3}\s?\d{2}\b/u', '', $part));
            if ($clean === '') {
                continue;
            }
            if ($street === null && preg_match('/\d/u', $clean)) {
                $street = $clean;
            } elseif ($city === null && ! preg_match('/\d/u', $clean)) {
                $city = $clean;
            }
        }

        return ['street' => $street, 'city' => $city, 'zip' => $zip];
    }
}
