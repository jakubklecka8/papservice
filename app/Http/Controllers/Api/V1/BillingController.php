<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Enums\ServiceType;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Tariff;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Ceník, cena u zakázky a podklady k vyúčtování (krok 12) */
class BillingController extends Controller
{
    public function __construct(private readonly PricingService $pricing) {}

    // ---------------------------------------------------------------- ceník

    public function tariffs(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canSeeMoney($user), 403);

        $q = Tariff::with('project:id,code,name')->orderBy('project_id')->orderBy('service_type');
        if (! $user->canReadAllProjects()) {
            $q->whereIn('project_id', $user->dispatcherProjectIds());
        }

        return response()->json(['data' => $q->get()->map(fn (Tariff $t) => $this->tariffPayload($t))]);
    }

    public function storeTariff(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $this->validateTariff($request, null);
        $tariff = Tariff::create($data);

        return response()->json(['data' => $this->tariffPayload($tariff->load('project'))], 201);
    }

    public function updateTariff(Request $request, Tariff $tariff): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $tariff->update($this->validateTariff($request, $tariff));

        return response()->json(['data' => $this->tariffPayload($tariff->fresh()->load('project'))]);
    }

    public function destroyTariff(Request $request, Tariff $tariff): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $tariff->delete();

        return response()->json(['ok' => true]);
    }

    // ---------------------------------------------------------------- cena zakázky

    public function updateTicketPrice(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('manage', $ticket);
        $money = ['nullable', 'numeric', 'min:0', 'max:9999999'];
        $data = $request->validate([
            'price_currency' => ['nullable', Rule::in(['CZK', 'EUR', 'PLN'])],
            'price_base' => $money,
            'price_km_rate' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'price_extra' => ['nullable', 'numeric', 'min:-9999999', 'max:9999999'],
            'price_total' => $money,
            'price_note' => ['nullable', 'string', 'max:255'],
        ]);
        $ticket->update($data);

        return response()->json(['data' => $this->pricing->quote($ticket->fresh())]);
    }

    // ---------------------------------------------------------------- vyúčtování

    /** Zakázky s výjezdem v období (podle data příjezdu), se spočítanou cenou */
    public function report(Request $request): JsonResponse
    {
        [$rows, $meta] = $this->rows($request);

        $totals = $rows->groupBy('currency')->map(fn (Collection $g, $cur) => [
            'currency' => $cur,
            'count' => $g->count(),
            'km' => round($g->sum('km'), 1),
            'total' => round($g->sum('total'), 2),
        ])->values();

        $byProject = $rows->groupBy(fn ($r) => $r['project'].'|'.$r['currency'])->map(fn (Collection $g) => [
            'project' => $g->first()['project'],
            'currency' => $g->first()['currency'],
            'count' => $g->count(),
            'km' => round($g->sum('km'), 1),
            'total' => round($g->sum('total'), 2),
        ])->values();

        $byTech = $rows->groupBy(fn ($r) => $r['technician'].'|'.$r['currency'])->map(fn (Collection $g) => [
            'technician' => $g->first()['technician'],
            'currency' => $g->first()['currency'],
            'count' => $g->count(),
            'km' => round($g->sum('km'), 1),
            'total' => round($g->sum('total'), 2),
        ])->values();

        return response()->json(['data' => $rows->values(), 'totals' => $totals, 'by_project' => $byProject, 'by_technician' => $byTech, 'meta' => $meta]);
    }

    /** CSV pro Excel (UTF-8 s BOM, středník) */
    public function export(Request $request): StreamedResponse
    {
        [$rows, $meta] = $this->rows($request);
        $name = 'vyuctovani_'.$meta['from'].'_'.$meta['to'].'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Číslo', 'RITM / ext.', 'Projekt', 'Typ', 'Provozovna', 'Město', 'Technik', 'Datum výjezdu', 'Stav',
                'Základ', 'Km', 'Sazba/km', 'Za km', 'Příplatky', 'Celkem', 'Měna', 'Ručně', 'Poznámka'], ';');
            $num = fn ($v) => str_replace('.', ',', (string) round((float) $v, 2));
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['number'], $r['external_ref'], $r['project'], $r['service_type'], $r['location'], $r['city'], $r['technician'],
                    $r['date'] ? Carbon::parse($r['date'])->timezone('Europe/Prague')->format('d.m.Y') : '', $r['status'],
                    $num($r['base']), $num($r['km']), $num($r['km_rate']), $num($r['km_amount']), $num($r['extra']), $num($r['total']),
                    $r['currency'], $r['manual_total'] ? 'ano' : '', $r['note'],
                ], ';');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: Collection<int, array<string, mixed>>, 1: array<string, mixed>} */
    private function rows(Request $request): array
    {
        $user = $request->user();
        abort_unless($this->canSeeMoney($user), 403);

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'project_id' => ['nullable', 'integer'],
            'technician_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['closed', 'done', 'all'])],
        ]);
        $tz = 'Europe/Prague';
        $from = isset($data['from']) ? Carbon::parse($data['from'], $tz)->startOfDay() : now($tz)->startOfMonth();
        $to = isset($data['to']) ? Carbon::parse($data['to'], $tz)->endOfDay() : now($tz)->endOfMonth();
        $status = $data['status'] ?? 'done';

        $q = Ticket::query()
            ->with(['project:id,code,name', 'location:id,code,name,city', 'technician:id,name', 'visits:id,ticket_id,km,arrived_at'])
            ->where('status', '!=', TicketStatus::Cancelled->value)
            ->whereHas('visits', fn ($v) => $v->whereBetween('arrived_at', [$from->copy()->utc(), $to->copy()->utc()]));

        if (! $user->canReadAllProjects()) {
            $q->whereIn('project_id', $user->dispatcherProjectIds());
        }
        if (! empty($data['project_id'])) {
            $q->where('project_id', $data['project_id']);
        }
        if (! empty($data['technician_id'])) {
            $q->where('assigned_technician_id', $data['technician_id']);
        }
        if ($status === 'closed') {
            $q->where('status', TicketStatus::Closed->value);
        } elseif ($status === 'done') {
            $q->whereIn('status', [TicketStatus::Completed->value, TicketStatus::Closed->value]);
        }

        $rows = $q->get()->map(function (Ticket $t) {
            $p = $this->pricing->quote($t);
            $date = $t->visits->max('arrived_at');

            return [
                'id' => $t->id,
                'number' => $t->number,
                'external_ref' => $t->external_ref,
                'project' => $t->project?->name,
                'project_id' => $t->project_id,
                'service_type' => $t->service_type,
                'location' => $t->location ? trim($t->location->name.($t->location->code ? ' ('.$t->location->code.')' : '')) : $t->title,
                'city' => $t->location?->city,
                'technician' => $t->technician?->name ?? '—',
                'date' => $date?->toIso8601String(),
                'status' => $t->status->value,
                'currency' => $p['currency'],
                'base' => $p['base'],
                'km' => $p['km'],
                'km_rate' => $p['km_rate'],
                'km_amount' => $p['km_amount'],
                'extra' => $p['extra'],
                'total' => $p['total'],
                'manual_total' => $p['manual_total'],
                'note' => $t->price_note,
            ];
        })->sortBy('date')->values();

        return [$rows, ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'status' => $status]];
    }

    private function canSeeMoney(User $user): bool
    {
        return $user->canReadAllProjects() || $user->hasRole(Role::Dispecer);
    }

    /** @return array<string, mixed> */
    private function validateTariff(Request $request, ?Tariff $tariff): array
    {
        $data = $request->validate([
            'project_id' => [$tariff ? 'sometimes' : 'required', 'integer', 'exists:projects,id'],
            'service_type' => ['nullable', Rule::enum(ServiceType::class)],
            'currency' => ['required', Rule::in(['CZK', 'EUR', 'PLN'])],
            'base_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'km_rate' => ['required', 'numeric', 'min:0', 'max:1000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $projectId = $data['project_id'] ?? $tariff->project_id;
        $type = $data['service_type'] ?? null;
        $dup = Tariff::where('project_id', $projectId)
            ->where(fn ($q) => $type === null ? $q->whereNull('service_type') : $q->where('service_type', $type))
            ->when($tariff, fn ($q) => $q->where('id', '!=', $tariff->id))
            ->exists();
        abort_if($dup, 422, 'Pro tento projekt a typ služby už ceník existuje.');

        return $data;
    }

    /** @return array<string, mixed> */
    private function tariffPayload(Tariff $t): array
    {
        return [
            'id' => $t->id,
            'project' => $t->project ? ['id' => $t->project->id, 'code' => $t->project->code, 'name' => $t->project->name] : null,
            'service_type' => $t->service_type,
            'currency' => $t->currency,
            'base_price' => $t->base_price,
            'km_rate' => $t->km_rate,
            'note' => $t->note,
        ];
    }
}
