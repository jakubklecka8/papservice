<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Services\Geocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PapFixtures;
use Tests\TestCase;

/** Trasy techniků – souřadnice, odhad km, optimalizace pořadí */
class RouteTest extends TestCase
{
    use PapFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedPap();
    }

    private function stop(string $code, float $lat, float $lng, string $time)
    {
        $loc = Location::create(['project_id' => $this->zas->id, 'code' => $code, 'name' => $code, 'city' => $code, 'lat' => $lat, 'lng' => $lng]);
        $t = $this->makeTicket($loc, $this->tech1);
        $t->update(['planned_at' => Carbon::parse('2026-12-01 '.$time, 'Europe/Prague')->utc()]);

        return $t;
    }

    public function test_route_estimate_and_optimize(): void
    {
        $this->tech1->update(['home_lat' => 49.7215, 'home_lng' => 18.0790]); // Studénka
        // schválně špatné pořadí: Opava, Frýdek, Ostrava, Nový Jičín
        $opava = $this->stop('OPAVA', 49.9387, 17.9026, '08:00');
        $frydek = $this->stop('FRYDEK', 49.6839, 18.3484, '09:00');
        $ostrava = $this->stop('OSTRAVA', 49.8209, 18.2625, '10:00');
        $nj = $this->stop('NJ', 49.5944, 18.0103, '11:00');

        Sanctum::actingAs($this->tech1);
        $before = $this->getJson('/api/v1/routes?date=2026-12-01')->assertOk()->json('data');
        $this->assertCount(4, $before['stops']);
        $this->assertSame('OPAVA', $before['stops'][0]['location']['code']);
        $this->assertGreaterThan(100, $before['total_km']);
        $this->postJson('/api/v1/routes/optimize', ['date' => '2026-12-01'])->assertForbidden();

        Sanctum::actingAs($this->dispecer);
        $after = $this->postJson('/api/v1/routes/optimize', ['technician_id' => $this->tech1->id, 'date' => '2026-12-01', 'start' => '07:30', 'service_minutes' => 60])
            ->assertOk()->json('data');
        $this->assertLessThan($before['total_km'], $after['total_km']);
        $codes = collect($after['stops'])->pluck('location.code')->all();
        // okruh: Opava a Ostrava vedle sebe, NJ a Frýdek vedle sebe
        $this->assertEqualsCanonicalizing(['OPAVA', 'OSTRAVA'], array_slice($codes, abs(array_search('OPAVA', $codes) - array_search('OSTRAVA', $codes)) === 1 ? min(array_search('OPAVA', $codes), array_search('OSTRAVA', $codes)) : 0, 2));
        $times = collect($after['stops'])->pluck('planned_at')->map(fn ($t) => Carbon::parse($t)->timezone('Europe/Prague'));
        $this->assertTrue($times[0]->format('H:i') >= '07:30' && $times[0]->format('H:i') < '08:30');
        foreach ($times as $i => $tm) {
            $this->assertSame(0, $tm->minute % 5);
            if ($i) {
                $this->assertTrue($tm->gt($times[$i - 1]->copy()->addMinutes(60)));
            }
        }
        $this->assertSame(4, $ostrava->history()->where('note', 'like', 'Trasa:%')->count() + $opava->history()->where('note', 'like', 'Trasa:%')->count()
            + $frydek->history()->where('note', 'like', 'Trasa:%')->count() + $nj->history()->where('note', 'like', 'Trasa:%')->count());

        Sanctum::actingAs($this->tech2);
        $this->getJson('/api/v1/routes?technician_id='.$this->tech1->id.'&date=2026-12-01')->assertForbidden();
    }

    public function test_geocoding_fills_coordinates(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::sequence()
                ->push([['lat' => '49.8209', 'lon' => '18.2625']])
                ->push([])->push([]),
        ]);
        Location::query()->update(['lat' => 50, 'lng' => 15]); // ostatní provozovny z fixtures už polohu mají
        $ok = Location::create(['project_id' => $this->zas->id, 'code' => 'L1', 'name' => 'L1', 'street' => 'Hlavní 1', 'city' => 'Ostrava', 'country' => 'CZ']);
        $bad = Location::create(['project_id' => $this->zas->id, 'code' => 'L2', 'name' => 'L2', 'street' => 'Nikde 1', 'city' => 'Xyz']);

        $this->assertSame(1, app(Geocoder::class)->batch(5));
        $this->assertEqualsWithDelta(49.8209, $ok->fresh()->lat, 0.0001);
        $this->assertSame('ok', $ok->fresh()->geocode_status);
        $this->assertSame('not_found', $bad->fresh()->geocode_status);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'countrycodes=cz') && $r->hasHeader('User-Agent'));
        // podruhé už se nehledá
        $this->assertSame(0, app(Geocoder::class)->batch(5));

        // změna adresy smaže souřadnice → dohledají se znovu
        Sanctum::actingAs($this->dispecer);
        $this->patchJson("/api/v1/locations/{$ok->id}", ['street' => 'Nová 2'])->assertOk();
        $this->assertNull($ok->fresh()->lat);
        $this->patchJson("/api/v1/locations/{$ok->id}", ['lat' => 49.5, 'lng' => 18.1])->assertOk();
        $this->assertSame('manual', $ok->fresh()->geocode_status);
    }
}
