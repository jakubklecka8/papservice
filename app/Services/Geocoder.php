<?php

namespace App\Services;

use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Souřadnice z adresy přes OpenStreetMap Nominatim (zdarma, max. 1 dotaz/s,
 * povinný User-Agent). Výsledek se ukládá k provozovně, takže se hledá jen jednou.
 */
class Geocoder
{
    private const URL = 'https://nominatim.openstreetmap.org/search';

    /** @return array{0: float, 1: float}|null */
    public function lookup(string $query, ?string $country = null): ?array
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query));
        if ($query === '') {
            return null;
        }
        $res = Http::timeout(8)
            ->withHeaders(['User-Agent' => 'PAPService/1.0 ('.(config('pap.admin_email') ?: 'admin@servicepap.com').')', 'Accept-Language' => 'cs,pl,en'])
            ->get(self::URL, array_filter([
                'q' => $query, 'format' => 'json', 'limit' => 1,
                'countrycodes' => $country ? strtolower($country) : null,
            ]));
        if (! $res->ok()) {
            throw new \RuntimeException('Nominatim HTTP '.$res->status());
        }
        $hit = $res->json(0);

        return $hit ? [(float) $hit['lat'], (float) $hit['lon']] : null;
    }

    public function location(Location $l): bool
    {
        $street = trim((string) $l->street);
        $city = trim(trim((string) $l->zip).' '.trim((string) $l->city));
        $tries = array_values(array_unique(array_filter([
            $street && $city ? $street.', '.$city : null,
            $city ?: null,                       // když ulice nesedí, aspoň město
        ])));
        try {
            foreach ($tries as $i => $q) {
                if ($i > 0) {
                    usleep(1_100_000);
                }
                if ($hit = $this->lookup($q, $l->country)) {
                    $l->forceFill(['lat' => $hit[0], 'lng' => $hit[1], 'geocoded_at' => now(), 'geocode_status' => $i === 0 ? 'ok' : 'city'])->save();

                    return true;
                }
            }
            $l->forceFill(['geocoded_at' => now(), 'geocode_status' => 'not_found'])->save();
        } catch (Throwable $e) {
            $l->forceFill(['geocoded_at' => now(), 'geocode_status' => 'error'])->save();
            report($e);
        }

        return false;
    }

    public function user(User $u): bool
    {
        if (! $u->home_address) {
            return false;
        }
        try {
            if ($hit = $this->lookup($u->home_address)) {
                $u->forceFill(['home_lat' => $hit[0], 'home_lng' => $hit[1]])->save();

                return true;
            }
        } catch (Throwable $e) {
            report($e);
        }

        return false;
    }

    /** Dávka pro cron: provozovny bez souřadnic (chyby se zkusí znovu po dni) @return int */
    public function batch(int $max = 5): int
    {
        $done = 0;
        $todo = Location::query()
            ->whereNull('lat')
            ->where(fn ($q) => $q->whereNotNull('city')->orWhereNotNull('street'))
            ->where(fn ($q) => $q->whereNull('geocoded_at')
                ->orWhere(fn ($w) => $w->where('geocode_status', 'error')->where('geocoded_at', '<', now()->subDay())))
            ->orderBy('id')->limit($max)->get();
        foreach ($todo as $i => $l) {
            if ($i > 0) {
                usleep(1_100_000);
            }
            $done += $this->location($l) ? 1 : 0;
        }

        return $done;
    }

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
