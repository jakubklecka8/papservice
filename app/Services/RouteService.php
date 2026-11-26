<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Denní trasa technika: zastávky = zakázky naplánované na daný den.
 * Vzdálenost je odhad (vzdušná čára × 1,3 – silnice nevedou rovně),
 * čas jízdy počítá s průměrnou rychlostí 55 km/h.
 */
class RouteService
{
    public const TZ = 'Europe/Prague';

    public const ROAD_FACTOR = 1.3;

    public const AVG_KMH = 55;

    /** @return Collection<int, Ticket> */
    public function stops(User $tech, string $date): Collection
    {
        $from = Carbon::parse($date, self::TZ)->startOfDay()->utc();
        $to = Carbon::parse($date, self::TZ)->endOfDay()->utc();

        return Ticket::query()
            ->with(['location', 'project:id,code,name'])
            ->where('assigned_technician_id', $tech->id)
            ->whereBetween('planned_at', [$from, $to])
            ->where('status', '!=', TicketStatus::Cancelled->value)
            ->orderBy('planned_at')->orderBy('id')
            ->get();
    }

    /** @return array<string, mixed> */
    public function build(User $tech, string $date): array
    {
        $stops = $this->stops($tech, $date);
        $home = $this->home($tech);

        $legs = [];
        $prev = $home;
        $total = 0.0;
        foreach ($stops as $t) {
            $pt = $this->point($t);
            $km = $prev && $pt ? $this->roadKm($prev, $pt) : null;
            $legs[] = $km;
            $total += $km ?? 0;
            if ($pt) {
                $prev = $pt;
            }
        }
        $back = $home && $prev && $prev !== $home ? $this->roadKm($prev, $home) : null;
        $total += $back ?? 0;
        $driveMin = (int) round($total / self::AVG_KMH * 60);

        return [
            'date' => $date,
            'technician' => ['id' => $tech->id, 'name' => $tech->name],
            'home' => $home ? ['lat' => $home[0], 'lng' => $home[1], 'address' => $tech->home_address] : ($tech->home_address ? ['lat' => null, 'lng' => null, 'address' => $tech->home_address] : null),
            'stops' => $stops->values()->map(fn (Ticket $t, $i) => [
                'ticket_id' => $t->id,
                'number' => $t->number,
                'external_ref' => $t->external_ref,
                'title' => $t->title,
                'status' => $t->status->value,
                'priority' => $t->priority,
                'project' => $t->project?->name,
                'planned_at' => $t->planned_at?->toIso8601String(),
                'location' => $t->location ? [
                    'id' => $t->location->id, 'code' => $t->location->code, 'name' => $t->location->name,
                    'address' => trim(implode(', ', array_filter([$t->location->street, trim($t->location->zip.' '.$t->location->city), $t->location->country]))),
                    'lat' => $t->location->lat !== null ? (float) $t->location->lat : null,
                    'lng' => $t->location->lng !== null ? (float) $t->location->lng : null,
                    'geocode_status' => $t->location->geocode_status,
                ] : null,
                'leg_km' => $legs[$i] !== null ? round($legs[$i], 1) : null,
            ]),
            'return_km' => $back !== null ? round($back, 1) : null,
            'total_km' => round($total, 1),
            'drive_minutes' => $driveMin,
            'missing_coords' => $stops->filter(fn ($t) => ! $this->point($t))->count(),
        ];
    }

    /**
     * Seřadí zastávky na nejkratší cestu (nejbližší soused + 2-opt) a přepočítá
     * časy: start, jízda, čas na místě. Zastávky bez souřadnic zůstanou na konci.
     *
     * @return array<string, mixed>
     */
    public function optimize(User $tech, string $date, string $start = '08:00', int $serviceMinutes = 45, ?User $by = null): array
    {
        $stops = $this->stops($tech, $date);
        if ($stops->isEmpty()) {
            return $this->build($tech, $date);
        }
        $with = $stops->filter(fn ($t) => $this->point($t))->values();
        $without = $stops->reject(fn ($t) => $this->point($t))->values();
        $home = $this->home($tech);

        $order = $this->nearestNeighbour($with, $home);
        $order = $this->twoOpt($order, $with, $home);
        $sorted = collect($order)->map(fn ($i) => $with[$i])->concat($without);

        $clock = Carbon::parse($date.' '.$start, self::TZ);
        $prev = $home;
        DB::transaction(function () use ($sorted, &$clock, &$prev, $serviceMinutes, $by) {
            foreach ($sorted as $i => $t) {
                $pt = $this->point($t);
                if ($prev && $pt) {
                    $clock->addMinutes((int) round($this->roadKm($prev, $pt) / self::AVG_KMH * 60));
                } elseif ($i > 0) {
                    $clock->addMinutes(20);
                }
                $clock = $this->roundUp($clock, 5);
                $t->update(['planned_at' => $clock->copy()->utc()]);
                $t->history()->create([
                    'from_status' => $t->status->value, 'to_status' => $t->status->value, 'user_id' => $by?->id,
                    'note' => 'Trasa: zastávka '.($i + 1).' v '.$clock->format('H:i'),
                ]);
                $clock->addMinutes($serviceMinutes);
                if ($pt) {
                    $prev = $pt;
                }
            }
        });

        return $this->build($tech, $date);
    }

    // ---------------------------------------------------------------- pomocné

    /** @return array{0: float, 1: float}|null */
    private function point(Ticket $t): ?array
    {
        $l = $t->location;

        return $l && $l->lat !== null && $l->lng !== null ? [(float) $l->lat, (float) $l->lng] : null;
    }

    /** @return array{0: float, 1: float}|null */
    private function home(User $u): ?array
    {
        return $u->home_lat !== null && $u->home_lng !== null ? [(float) $u->home_lat, (float) $u->home_lng] : null;
    }

    private function roadKm(array $a, array $b): float
    {
        return Geocoder::distanceKm($a[0], $a[1], $b[0], $b[1]) * self::ROAD_FACTOR;
    }

    /** @return list<int> */
    private function nearestNeighbour(Collection $pts, ?array $home): array
    {
        $left = range(0, $pts->count() - 1);
        $order = [];
        // bez domova začni zastávkou, která je naplánovaná nejdřív
        $cur = $home;
        if (! $cur) {
            $first = array_shift($left);
            $order[] = $first;
            $cur = $this->point($pts[$first]);
        }
        while ($left) {
            usort($left, fn ($a, $b) => $this->roadKm($cur, $this->point($pts[$a])) <=> $this->roadKm($cur, $this->point($pts[$b])));
            $next = array_shift($left);
            $order[] = $next;
            $cur = $this->point($pts[$next]);
        }

        return $order;
    }

    /** @param list<int> $order @return list<int> */
    private function twoOpt(array $order, Collection $pts, ?array $home): array
    {
        $n = count($order);
        if ($n < 4) {
            return $order;
        }
        $p = fn ($i) => $this->point($pts[$i]);
        $len = function (array $o) use ($p, $home) {
            $sum = 0;
            $prev = $home;
            foreach ($o as $i) {
                if ($prev) {
                    $sum += $this->roadKm($prev, $p($i));
                }
                $prev = $p($i);
            }

            return $sum + ($home ? $this->roadKm($prev, $home) : 0);
        };
        $best = $len($order);
        $improved = true;
        $guard = 0;
        $startAt = $home ? 0 : 1; // bez domova první zastávka zůstává
        while ($improved && $guard++ < 50) {
            $improved = false;
            for ($i = $startAt; $i < $n - 1; $i++) {
                for ($k = $i + 1; $k < $n; $k++) {
                    $cand = array_merge(array_slice($order, 0, $i), array_reverse(array_slice($order, $i, $k - $i + 1)), array_slice($order, $k + 1));
                    $l = $len($cand);
                    if ($l + 0.01 < $best) {
                        [$order, $best, $improved] = [$cand, $l, true];
                    }
                }
            }
        }

        return $order;
    }

    private function roundUp(Carbon $c, int $step): Carbon
    {
        $m = (int) $c->format('i');
        $r = (int) (ceil($m / $step) * $step);

        return $c->copy()->setTime((int) $c->format('H'), 0)->addMinutes($r);
    }
}
