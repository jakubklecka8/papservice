<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Geocoder;
use App\Services\RouteService;
use App\Services\TechnicianNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Denní trasa technika (krok 09 – trasy) */
class RouteController extends Controller
{
    public function __construct(private readonly RouteService $routes) {}

    public function show(Request $request): JsonResponse
    {
        [$tech, $date] = $this->target($request);

        return response()->json(['data' => $this->routes->build($tech, $date)]);
    }

    /** Seřadit zastávky na nejkratší cestu a přepočítat časy */
    public function optimize(Request $request): JsonResponse
    {
        $this->requireDispatch($request->user());
        [$tech, $date] = $this->target($request);
        $data = $request->validate([
            'start' => ['nullable', 'date_format:H:i'],
            'service_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
        ]);
        $route = $this->routes->optimize($tech, $date, $data['start'] ?? '08:00', $data['service_minutes'] ?? 45, $request->user());
        if (count($route['stops'])) {
            app(TechnicianNotifier::class)->routeChanged($tech, $date, count($route['stops']));
        }

        return response()->json(['data' => $route]);
    }

    /** Dohledat chybějící souřadnice zastávek hned (max. 8, ~1 s na adresu) */
    public function geocode(Request $request): JsonResponse
    {
        $this->requireDispatch($request->user());
        [$tech, $date] = $this->target($request);
        $geo = app(Geocoder::class);
        if ($tech->home_address && $tech->home_lat === null) {
            $geo->user($tech);
        }
        $n = 0;
        foreach ($this->routes->stops($tech, $date)->pluck('location')->filter()->unique('id') as $loc) {
            if ($loc->lat === null && $n < 8) {
                if ($n > 0) {
                    usleep(1_100_000);
                }
                $geo->location($loc);
                $n++;
            }
        }

        return response()->json(['data' => $this->routes->build($tech->fresh(), $date)]);
    }

    /** @return array{0: User, 1: string} */
    private function target(Request $request): array
    {
        $data = $request->validate([
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $user = $request->user();
        $tech = ! empty($data['technician_id']) ? User::findOrFail($data['technician_id']) : $user;
        if ($tech->id !== $user->id) {
            $this->requireDispatch($user);
        }

        return [$tech, $data['date'] ?? now(RouteService::TZ)->toDateString()];
    }

    private function requireDispatch(User $user): void
    {
        abort_unless($user->isAdmin() || $user->hasRole(Role::Dispecer), 403);
    }
}
