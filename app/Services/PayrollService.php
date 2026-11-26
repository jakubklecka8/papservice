<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\Expense;
use App\Models\Payout;
use App\Models\PayRate;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Měsíční přehled technika: odměny za výjezdy, náhrada za km, hodiny na místě
 * a schválené výdaje, které platil ze svého. Výjezd se počítá do měsíce podle
 * data příjezdu a jen když je odeslaný/schválený (hotová práce).
 */
class PayrollService
{
    public const TZ = 'Europe/Prague';

    /** @return array{0: Carbon, 1: Carbon} */
    public function range(string $month): array
    {
        $from = Carbon::parse($month.'-01', self::TZ)->startOfMonth();

        return [$from, $from->copy()->endOfMonth()];
    }

    /** @return array<string, mixed> */
    public function statement(User $tech, string $month): array
    {
        if ($payout = Payout::where('user_id', $tech->id)->where('month', $month)->first()) {
            return $payout->data + ['payout' => $this->payoutMeta($payout)];
        }

        return $this->compute($tech, $month) + ['payout' => null];
    }

    /** @return array<string, mixed> */
    public function compute(User $tech, string $month): array
    {
        [$from, $to] = $this->range($month);
        $rates = PayRate::all();

        $visits = Visit::query()
            ->with(['ticket:id,number,external_ref,title,project_id,service_type,location_id', 'ticket.project:id,name', 'ticket.location:id,code,name,city'])
            ->where('technician_id', $tech->id)
            ->whereIn('status', [VisitStatus::Submitted->value, VisitStatus::Approved->value])
            ->whereBetween('arrived_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->orderBy('arrived_at')
            ->get();

        $rows = $visits->map(function (Visit $v) use ($rates, $tech) {
            $t = $v->ticket;
            $rate = PayRate::pick($rates, $tech->id, $t->project_id, $t->service_type);
            $km = (float) ($v->km ?? 0);
            $hours = round(($v->minutes_on_site ?? 0) / 60, 2);
            $reward = $rate?->per_visit ?? 0;
            $kmPay = round($km * ($rate?->per_km ?? 0), 2);
            $hourPay = round($hours * ($rate?->per_hour ?? 0), 2);

            return [
                'visit_uuid' => $v->uuid,
                'ticket_id' => $t->id,
                'number' => $t->number,
                'external_ref' => $t->external_ref,
                'project_id' => $t->project_id,
                'project' => $t->project?->name,
                'service_type' => $t->service_type,
                'location' => $t->location ? trim($t->location->code.' · '.$t->location->name) : $t->title,
                'date' => $v->arrived_at?->toIso8601String(),
                'km' => $km,
                'hours' => $hours,
                'currency' => $rate?->currency ?? 'CZK',
                'reward' => (float) $reward,
                'km_pay' => $kmPay,
                'hour_pay' => $hourPay,
                'total' => round($reward + $kmPay + $hourPay, 2),
                'rate_missing' => $rate === null,
            ];
        })->values();

        // výdaje, které technik zaplatil ze svého a ekonom schválil (ještě nevyplacené jinde)
        $expenses = Expense::query()
            ->where('user_id', $tech->id)->where('paid_with', 'own')
            ->whereIn('status', ['approved', 'paid'])
            ->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->orderBy('spent_on')->get()
            ->map(fn (Expense $e) => [
                'id' => $e->id, 'date' => $e->spent_on->toDateString(), 'category' => $e->category, 'description' => $e->description,
                'amount' => $e->amount, 'currency' => $e->currency, 'status' => $e->status,
            ])->values();

        $currencies = $rows->pluck('currency')->merge($expenses->pluck('currency'))->unique()->values();
        $totals = $currencies->map(function ($cur) use ($rows, $expenses) {
            $r = $rows->where('currency', $cur);
            $e = $expenses->where('currency', $cur);

            return [
                'currency' => $cur,
                'visits' => $r->count(),
                'km' => round($r->sum('km'), 1),
                'hours' => round($r->sum('hours'), 2),
                'rewards' => round($r->sum('reward'), 2),
                'km_pay' => round($r->sum('km_pay'), 2),
                'hour_pay' => round($r->sum('hour_pay'), 2),
                'expenses' => round($e->sum('amount'), 2),
                'total' => round($r->sum('total') + $e->sum('amount'), 2),
            ];
        })->values();

        return [
            'month' => $month,
            'technician' => ['id' => $tech->id, 'name' => $tech->name, 'settlement_mode' => $tech->settlement_mode ?? 'reimburse', 'ico' => $tech->ico],
            'rows' => $rows->all(),
            'expenses' => $expenses->all(),
            'totals' => $totals->all(),
            'rate_missing' => $rows->where('rate_missing', true)->count(),
            'computed_at' => now()->toIso8601String(),
        ];
    }

    /** Ekonom potvrdí vyúčtování – čísla se zamrazí */
    public function confirm(User $tech, string $month, User $by): Payout
    {
        $data = $this->compute($tech, $month);
        $total = collect($data['totals'])->firstWhere('currency', 'CZK')['total'] ?? (collect($data['totals'])->first()['total'] ?? 0);
        $currency = collect($data['totals'])->firstWhere('currency', 'CZK') ? 'CZK' : (collect($data['totals'])->first()['currency'] ?? 'CZK');

        $payout = Payout::create([
            'user_id' => $tech->id, 'month' => $month, 'currency' => $currency, 'total' => $total, 'data' => $data,
            'status' => 'confirmed', 'confirmed_by' => $by->id, 'confirmed_at' => now(),
        ]);
        Expense::whereIn('id', collect($data['expenses'])->pluck('id'))->update(['payout_id' => $payout->id]);

        return $payout;
    }

    public function markPaid(Payout $payout, User $by): void
    {
        $payout->update(['status' => 'paid', 'paid_by' => $by->id, 'paid_at' => now()]);
        Expense::where('payout_id', $payout->id)->where('status', 'approved')
            ->update(['status' => 'paid', 'paid_by' => $by->id, 'paid_at' => now()]);
    }

    /** @return array<string, mixed> */
    public function payoutMeta(Payout $p): array
    {
        $p->loadMissing(['user:id,name']);

        return [
            'id' => $p->id, 'status' => $p->status, 'total' => $p->total, 'currency' => $p->currency,
            'confirmed_at' => $p->confirmed_at?->toIso8601String(), 'paid_at' => $p->paid_at?->toIso8601String(),
            'confirmed_by' => User::whereKey($p->confirmed_by)->value('name'),
            'paid_by' => $p->paid_by ? User::whereKey($p->paid_by)->value('name') : null,
        ];
    }

    /** Technici s prací nebo výdaji v měsíci – přehled pro ekonoma @return Collection<int, array> */
    public function overview(string $month): Collection
    {
        [$from, $to] = $this->range($month);
        $ids = Visit::whereIn('status', [VisitStatus::Submitted->value, VisitStatus::Approved->value])
            ->whereBetween('arrived_at', [$from->copy()->utc(), $to->copy()->utc()])->distinct()->pluck('technician_id')
            ->merge(Expense::whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])->distinct()->pluck('user_id'))
            ->merge(Payout::where('month', $month)->pluck('user_id'))
            ->unique();

        return User::whereIn('id', $ids)->orderBy('name')->get()->map(function (User $u) use ($month) {
            $s = $this->statement($u, $month);

            return [
                'technician' => $s['technician'],
                'totals' => $s['totals'],
                'rate_missing' => $s['rate_missing'],
                'payout' => $s['payout'],
            ];
        })->values();
    }

    /**
     * Náklady a příjmy projektů za měsíc: fakturace zákazníkovi (ceník/cena zakázky)
     * vs. odměny techniků a schválené výdaje přiřazené k projektu.
     *
     * @return list<array<string, mixed>>
     */
    public function projectCosts(string $month, ?array $projectIds = null): array
    {
        [$from, $to] = $this->range($month);
        $pricing = app(PricingService::class);
        $out = [];
        $add = function (int $pid, string $cur, string $key, float $val) use (&$out) {
            $k = $pid.'|'.$cur;
            $out[$k] ??= ['project_id' => $pid, 'currency' => $cur, 'revenue' => 0.0, 'labour' => 0.0, 'expenses' => 0.0, 'tickets' => 0, 'visits' => 0, 'hours' => 0.0];
            $out[$k][$key] += $val;
        };

        // příjmy – zakázky s výjezdem v měsíci (stejně jako Vyúčtování → Fakturace)
        $tickets = Ticket::query()->with('visits:id,ticket_id,km,arrived_at')
            ->where('status', '!=', 'cancelled')
            ->when($projectIds !== null, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->whereHas('visits', fn ($v) => $v->whereBetween('arrived_at', [$from->copy()->utc(), $to->copy()->utc()]))
            ->get();
        foreach ($tickets as $t) {
            $q = $pricing->quote($t);
            $add($t->project_id, $q['currency'], 'revenue', $q['total']);
            $add($t->project_id, $q['currency'], 'tickets', 1);
        }

        // práce techniků
        $rates = PayRate::all();
        $visits = Visit::with('ticket:id,project_id,service_type')
            ->whereIn('status', [VisitStatus::Submitted->value, VisitStatus::Approved->value])
            ->whereBetween('arrived_at', [$from->copy()->utc(), $to->copy()->utc()])->get();
        foreach ($visits as $v) {
            if ($projectIds !== null && ! in_array($v->ticket->project_id, $projectIds, true)) {
                continue;
            }
            $r = PayRate::pick($rates, $v->technician_id, $v->ticket->project_id, $v->ticket->service_type);
            $hours = ($v->minutes_on_site ?? 0) / 60;
            $cost = ($r?->per_visit ?? 0) + ($v->km ?? 0) * ($r?->per_km ?? 0) + $hours * ($r?->per_hour ?? 0);
            $cur = $r?->currency ?? 'CZK';
            $add($v->ticket->project_id, $cur, 'labour', $cost);
            $add($v->ticket->project_id, $cur, 'visits', 1);
            $add($v->ticket->project_id, $cur, 'hours', $hours);
        }

        // výdaje (schválené/proplacené, i firemní kartou)
        $expenses = Expense::whereIn('status', ['approved', 'paid'])->whereNotNull('project_id')
            ->when($projectIds !== null, fn ($q) => $q->whereIn('project_id', $projectIds))
            ->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])->get();
        foreach ($expenses as $e) {
            $add($e->project_id, $e->currency, 'expenses', $e->amount);
        }

        $names = Project::pluck('name', 'id');

        return collect($out)->map(fn ($r) => $r + [
            'project' => $names[$r['project_id']] ?? '?',
            'margin' => round($r['revenue'] - $r['labour'] - $r['expenses'], 2),
        ])->map(fn ($r) => array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $r))
            ->sortBy('project')->values()->all();
    }
}
