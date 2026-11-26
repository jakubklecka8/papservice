<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotification;
use App\Models\Expense;
use App\Models\Payout;
use App\Models\PayRate;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Odměny techniků, měsíční vyúčtování a náklady projektů (krok 11) */
class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payroll) {}

    // ---------------------------------------------------------------- sazby

    public function rates(Request $request): JsonResponse
    {
        $this->requireFinance($request->user(), true);
        $rows = PayRate::with(['user:id,name', 'project:id,name'])->orderByRaw('user_id is null desc')->orderBy('user_id')->orderBy('project_id')->get();

        return response()->json(['data' => $rows->map(fn (PayRate $r) => $this->ratePayload($r))]);
    }

    public function storeRate(Request $request): JsonResponse
    {
        $this->requireFinance($request->user());
        $r = PayRate::create($this->validateRate($request, null));

        return response()->json(['data' => $this->ratePayload($r->load(['user', 'project']))], 201);
    }

    public function updateRate(Request $request, PayRate $rate): JsonResponse
    {
        $this->requireFinance($request->user());
        $rate->update($this->validateRate($request, $rate));

        return response()->json(['data' => $this->ratePayload($rate->fresh(['user', 'project']))]);
    }

    public function destroyRate(Request $request, PayRate $rate): JsonResponse
    {
        $this->requireFinance($request->user());
        $rate->delete();

        return response()->json(['ok' => true]);
    }

    // ---------------------------------------------------------------- vyúčtování techniků

    public function overview(Request $request): JsonResponse
    {
        $this->requireFinance($request->user(), true);
        $month = $this->month($request);

        return response()->json(['data' => $this->payroll->overview($month), 'month' => $month]);
    }

    /** Přehled jednoho technika – technik vidí jen svůj */
    public function statement(Request $request): JsonResponse
    {
        $user = $request->user();
        $month = $this->month($request);
        $techId = $request->integer('user_id') ?: $user->id;
        if ($techId !== $user->id) {
            $this->requireFinance($user, true);
        }

        return response()->json(['data' => $this->payroll->statement(User::findOrFail($techId), $month)]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $this->requireFinance($request->user());
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'month' => ['required', 'date_format:Y-m']]);
        abort_if(Payout::where('user_id', $data['user_id'])->where('month', $data['month'])->exists(), 422, 'Vyúčtování za tento měsíc už je potvrzené.');
        $payout = $this->payroll->confirm(User::findOrFail($data['user_id']), $data['month'], $request->user());

        return response()->json(['data' => $this->payroll->payoutMeta($payout)], 201);
    }

    public function pay(Request $request, Payout $payout): JsonResponse
    {
        $this->requireFinance($request->user());
        abort_unless($payout->status === 'confirmed', 422, 'Vyúčtování už je uhrazené.');
        $this->payroll->markPaid($payout, $request->user());
        SendPushNotification::dispatch($payout->user_id, [
            'title' => '💰 Vyúčtování '.$payout->month.' uhrazeno',
            'body' => number_format($payout->total, 0, ',', ' ').' '.$payout->currency,
            'url' => '/technik/#/earnings?month='.$payout->month, 'tag' => 'payout-'.$payout->month,
        ]);

        return response()->json(['data' => $this->payroll->payoutMeta($payout->fresh())]);
    }

    /** Zrušit potvrzení (jen neuhrazené) – čísla se znovu počítají živě */
    public function unconfirm(Request $request, Payout $payout): JsonResponse
    {
        $this->requireFinance($request->user());
        abort_unless($payout->status === 'confirmed', 422, 'Uhrazené vyúčtování nejde vrátit.');
        Expense::where('payout_id', $payout->id)->update(['payout_id' => null]);
        $payout->delete();

        return response()->json(['ok' => true]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->requireFinance($request->user(), true);
        $month = $this->month($request);
        $techs = $request->integer('user_id')
            ? collect([User::findOrFail($request->integer('user_id'))])
            : User::whereIn('id', $this->payroll->overview($month)->pluck('technician.id'))->orderBy('name')->get();

        return response()->streamDownload(function () use ($techs, $month) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $n = fn ($v) => str_replace('.', ',', (string) round((float) $v, 2));
            fputcsv($out, ['Technik', 'Způsob', 'Datum', 'Zakázka', 'RITM / ext.', 'Projekt', 'Provozovna', 'Km', 'Hodiny', 'Odměna', 'Za km', 'Za hodiny', 'Výdaj', 'Celkem', 'Měna'], ';');
            foreach ($techs as $u) {
                $s = $this->payroll->statement($u, $month);
                $mode = ($s['technician']['settlement_mode'] ?? 'reimburse') === 'invoice' ? 'faktura' : 'proplacení';
                foreach ($s['rows'] as $r) {
                    fputcsv($out, [$u->name, $mode, substr($r['date'], 0, 10), $r['number'], $r['external_ref'], $r['project'], $r['location'],
                        $n($r['km']), $n($r['hours']), $n($r['reward']), $n($r['km_pay']), $n($r['hour_pay']), '', $n($r['total']), $r['currency']], ';');
                }
                foreach ($s['expenses'] as $e) {
                    fputcsv($out, [$u->name, $mode, $e['date'], '', '', '', 'Výdaj: '.$e['category'].($e['description'] ? ' – '.$e['description'] : ''),
                        '', '', '', '', '', $n($e['amount']), $n($e['amount']), $e['currency']], ';');
                }
                foreach ($s['totals'] as $t) {
                    fputcsv($out, [$u->name.' – CELKEM', $mode, '', $t['visits'].' výjezdů', '', '', '', $n($t['km']), $n($t['hours']), $n($t['rewards']), $n($t['km_pay']), $n($t['hour_pay']), $n($t['expenses']), $n($t['total']), $t['currency']], ';');
                }
            }
            fclose($out);
        }, 'odmeny_'.$month.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ---------------------------------------------------------------- náklady projektů

    public function projects(Request $request): JsonResponse
    {
        $this->requireFinance($request->user(), true);
        $month = $this->month($request);

        return response()->json(['data' => $this->payroll->projectCosts($month), 'month' => $month]);
    }

    // ----------------------------------------------------------------

    private function month(Request $request): string
    {
        $m = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? null;

        return $m ?: now(PayrollService::TZ)->format('Y-m');
    }

    /** ekonom + admin mění, společník jen čte */
    private function requireFinance(User $u, bool $read = false): void
    {
        $ok = $u->isAdmin() || $u->hasRole(Role::Ekonom) || ($read && $u->hasRole(Role::Spolecnik));
        abort_unless($ok, 403);
    }

    /** @return array<string, mixed> */
    private function validateRate(Request $request, ?PayRate $rate): array
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'service_type' => ['nullable', Rule::in(['servis', 'profilaxe', 'udrzba', 'instalace'])],
            'currency' => ['required', Rule::in(['CZK', 'EUR', 'PLN'])],
            'per_visit' => ['required', 'numeric', 'min:0', 'max:999999'],
            'per_km' => ['required', 'numeric', 'min:0', 'max:1000'],
            'per_hour' => ['required', 'numeric', 'min:0', 'max:100000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $dup = PayRate::query()
            ->where(fn ($q) => isset($data['user_id']) ? $q->where('user_id', $data['user_id']) : $q->whereNull('user_id'))
            ->where(fn ($q) => isset($data['project_id']) ? $q->where('project_id', $data['project_id']) : $q->whereNull('project_id'))
            ->where(fn ($q) => isset($data['service_type']) ? $q->where('service_type', $data['service_type']) : $q->whereNull('service_type'))
            ->when($rate, fn ($q) => $q->where('id', '!=', $rate->id))->exists();
        abort_if($dup, 422, 'Taková sazba už existuje.');

        return $data + ['user_id' => null, 'project_id' => null, 'service_type' => null];
    }

    /** @return array<string, mixed> */
    private function ratePayload(PayRate $r): array
    {
        return [
            'id' => $r->id,
            'user' => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name] : null,
            'project' => $r->project ? ['id' => $r->project->id, 'name' => $r->project->name] : null,
            'service_type' => $r->service_type,
            'currency' => $r->currency,
            'per_visit' => $r->per_visit,
            'per_km' => $r->per_km,
            'per_hour' => $r->per_hour,
            'note' => $r->note,
        ];
    }
}
