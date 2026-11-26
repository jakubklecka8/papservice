<?php

namespace App\Services\Mail;

use App\Models\Location;
use Illuminate\Support\Str;

/**
 * Dohledá provozovnu v databázi podle textu e-mailu a nápověd od AI.
 * Vrací jistou shodu (location) nebo seznam kandidátů pro dispečera.
 */
class LocationMatcher
{
    /**
     * @param  array<string, ?string>  $hints
     * @return array{location: ?Location, candidates: list<array{id:int, code:string, name:string, city:?string, score:float}>}
     */
    public function match(int $projectId, string $text, array $hints = []): array
    {
        $scores = [];
        $haystack = ' '.Str::lower(Str::ascii($text)).' ';

        // 1) kód provozovny přímo v textu (nejsilnější signál)
        $codes = Location::where('project_id', $projectId)->where('is_active', true)->pluck('code', 'id');
        foreach ($codes as $id => $code) {
            $c = Str::lower(Str::ascii($code));
            if (mb_strlen($c) >= 3 && preg_match('/(?<![a-z0-9])'.preg_quote($c, '/').'(?![a-z0-9])/', $haystack)) {
                $scores[$id] = max($scores[$id] ?? 0, 1.0);
            }
        }

        // 2) nápovědy od AI
        if (! empty($hints['code'])) {
            $hit = Location::where('project_id', $projectId)->whereRaw('LOWER(code) = ?', [Str::lower($hints['code'])])->value('id');
            if ($hit) {
                $scores[$hit] = max($scores[$hit] ?? 0, 1.0);
            }
        }
        $city = $hints['city'] ?? null;
        $street = $hints['street'] ?? null;
        $name = $hints['store_name'] ?? ($hints['brand'] ?? null);
        if ($city || $street || $name || ! empty($hints['zip'])) {
            $q = Location::where('project_id', $projectId)->where('is_active', true);
            $q->where(function ($w) use ($city, $street, $name, $hints) {
                if ($city) {
                    $w->orWhere('city', 'like', '%'.$city.'%');
                }
                if (! empty($hints['zip'])) {
                    $w->orWhere('zip', 'like', '%'.preg_replace('/\s+/', '', $hints['zip']).'%')->orWhere('zip', $hints['zip']);
                }
                if ($street) {
                    $w->orWhere('street', 'like', '%'.$this->streetCore($street).'%');
                }
                if ($name) {
                    $w->orWhere('name', 'like', '%'.$name.'%');
                }
            });
            foreach ($q->limit(50)->get() as $loc) {
                $s = 0.0;
                if ($city && Str::contains(Str::lower(Str::ascii((string) $loc->city)), Str::lower(Str::ascii($city)))) {
                    $s += 0.35;
                }
                if (! empty($hints['zip']) && preg_replace('/\s+/', '', (string) $loc->zip) === preg_replace('/\s+/', '', $hints['zip'])) {
                    $s += 0.25;
                }
                if ($street && Str::contains(Str::lower(Str::ascii((string) $loc->street)), Str::lower(Str::ascii($this->streetCore($street))))) {
                    $s += 0.4;
                }
                if ($name && Str::contains(Str::lower(Str::ascii($loc->name)), Str::lower(Str::ascii($name)))) {
                    $s += 0.2;
                }
                if ($s > 0) {
                    $scores[$loc->id] = max($scores[$loc->id] ?? 0, min(0.95, $s));
                }
            }
        }

        arsort($scores);
        $top = array_slice($scores, 0, 5, true);
        $locs = Location::whereIn('id', array_keys($top))->get()->keyBy('id');
        $candidates = [];
        foreach ($top as $id => $score) {
            if ($l = $locs[$id] ?? null) {
                $candidates[] = ['id' => $l->id, 'code' => $l->code, 'name' => $l->name, 'city' => $l->city, 'score' => round($score, 2)];
            }
        }

        // jistá shoda: jediný kandidát se silným skóre, nebo výrazně lepší první
        $location = null;
        $first = $candidates[0] ?? null;
        $second = $candidates[1] ?? null;
        if ($first && $first['score'] >= 0.75 && (! $second || $first['score'] - $second['score'] >= 0.3)) {
            $location = $locs[$first['id']];
        }

        return ['location' => $location, 'candidates' => $candidates];
    }

    private function streetCore(string $street): string
    {
        // „Masarykova 12a“ → „Masarykova“ (číslo popisné se v DB píše různě)
        return trim(preg_replace('/\s*\d+[a-z]?(\/\d+[a-z]?)?\s*$/iu', '', $street)) ?: $street;
    }
}
