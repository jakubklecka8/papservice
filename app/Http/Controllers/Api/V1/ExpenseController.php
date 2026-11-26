<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotification;
use App\Models\Expense;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Výdaje s dokladem (krok 11): technik přidá doklad → dispečer zkontroluje →
 * ekonom schválí → ekonom potvrdí úhradu. Vrácený výdaj technik opraví a odešle znovu.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'status' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'date_format:Y-m'],
            'mine' => ['nullable', 'boolean'],
        ]);
        $q = Expense::query()->with(['user:id,name', 'project:id,name', 'ticket:id,number', 'checker:id,name', 'approver:id,name'])
            ->orderByDesc('spent_on')->orderByDesc('id');

        if (! empty($data['mine']) || ! $this->isOffice($user)) {
            $q->where('user_id', $user->id);
        } elseif (! empty($data['user_id'])) {
            $q->where('user_id', $data['user_id']);
        }
        if (! empty($data['status'])) {
            $q->whereIn('status', explode(',', $data['status']));
        }
        if (! empty($data['month'])) {
            $q->where('spent_on', '>=', $data['month'].'-01')->where('spent_on', '<=', date('Y-m-t', strtotime($data['month'].'-01')));
        }

        $rows = $q->limit(500)->get();
        $counts = $this->isOffice($user) && empty($data['mine'])
            ? Expense::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')
            : null;

        return response()->json(['data' => $rows->map(fn ($e) => $this->payload($e)), 'counts' => $counts]);
    }

    /** Technik přidá výdaj (multipart s fotkou dokladu). Idempotentní podle uuid. */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $this->validated($request, true);

        if ($existing = Expense::where('uuid', strtolower($data['uuid']))->first()) {
            abort_unless($existing->user_id === $user->id, 409, 'UUID už je použité.');

            return response()->json(['data' => $this->payload($existing)]);
        }
        $uuid = strtolower($data['uuid']);
        $data = $this->withTicket($data, $user);
        $e = new Expense($data + ['uuid' => $uuid, 'user_id' => $user->id, 'status' => 'submitted']);
        $this->storeReceipt($request, $e);
        $e->save();
        $this->notifyOffice('dispecer', '🧾 Nový výdaj: '.$user->name, $this->line($e));

        return response()->json(['data' => $this->payload($e->load(['user', 'project', 'ticket']))], 201);
    }

    /** Technik opraví vrácený/čekající výdaj (POST kvůli multipartu) */
    public function update(Request $request, Expense $expense): JsonResponse
    {
        $user = $request->user();
        abort_unless($expense->user_id === $user->id && $expense->isEditableByOwner(), 403);
        $data = $this->withTicket($this->validated($request, false), $user);
        $expense->fill($data);
        $this->storeReceipt($request, $expense);
        if ($expense->status === 'returned') {
            $expense->status = 'submitted';
            $expense->return_note = null;
        }
        $expense->save();

        return response()->json(['data' => $this->payload($expense->fresh(['user', 'project', 'ticket']))]);
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        abort_unless($expense->user_id === $request->user()->id && $expense->isEditableByOwner(), 403);
        if ($expense->receipt_path) {
            Storage::disk('local')->delete($expense->receipt_path);
        }
        $expense->delete();

        return response()->json(['ok' => true]);
    }

    /** check (dispečer) | approve (ekonom) | pay (ekonom) | return | reject */
    public function action(Request $request, Expense $expense): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'action' => ['required', Rule::in(['check', 'approve', 'pay', 'return', 'reject'])],
            'note' => ['nullable', 'string', 'max:1000'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);
        $isEkonom = $user->isAdmin() || $user->hasRole(Role::Ekonom);
        $isDispatch = $user->isAdmin() || $user->hasRole(Role::Dispecer);
        $s = $expense->status;

        switch ($data['action']) {
            case 'check':
                abort_unless($isDispatch && $s === 'submitted', 422, 'Výdaj nejde zkontrolovat.');
                $expense->fill(['status' => 'checked', 'checked_by' => $user->id, 'checked_at' => now()]);
                break;
            case 'approve':
                // ekonom může schválit i nezkontrolovaný (např. firemní karta)
                abort_unless($isEkonom && in_array($s, ['submitted', 'checked'], true), 422, 'Výdaj nejde schválit.');
                $expense->fill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
                break;
            case 'pay':
                abort_unless($isEkonom && $s === 'approved', 422, 'Uhradit jde jen schválený výdaj.');
                $expense->fill(['status' => 'paid', 'paid_by' => $user->id, 'paid_at' => now()]);
                break;
            case 'return':
                abort_unless(($isDispatch || $isEkonom) && in_array($s, ['submitted', 'checked', 'approved'], true), 422, 'Výdaj nejde vrátit.');
                abort_if(empty($data['note']), 422, 'Napiš, co má technik opravit.');
                $expense->fill(['status' => 'returned', 'return_note' => $data['note'], 'checked_by' => null, 'checked_at' => null, 'approved_by' => null, 'approved_at' => null]);
                break;
            case 'reject':
                abort_unless(($isDispatch || $isEkonom) && in_array($s, ['submitted', 'checked', 'approved', 'returned'], true), 422, 'Výdaj nejde zamítnout.');
                $expense->fill(['status' => 'rejected', 'return_note' => $data['note'] ?? null]);
                break;
        }
        if (array_key_exists('project_id', $data) && $data['project_id'] !== null) {
            $expense->project_id = $data['project_id'];
        }
        $expense->save();

        if (in_array($data['action'], ['return', 'reject'], true)) {
            $this->push($expense->user, ($data['action'] === 'return' ? '↩ Výdaj vrácen' : '✕ Výdaj zamítnut'), $this->line($expense).($data['note'] ? ' – '.$data['note'] : ''));
        } elseif ($data['action'] === 'check') {
            $this->notifyOffice('ekonom', '🧾 Výdaj ke schválení: '.$expense->user->name, $this->line($expense));
        } elseif ($data['action'] === 'pay') {
            $this->push($expense->user, '✓ Výdaj proplacen', $this->line($expense));
        }

        return response()->json(['data' => $this->payload($expense->fresh(['user', 'project', 'ticket', 'checker', 'approver']))]);
    }

    public function receipt(Request $request, Expense $expense): StreamedResponse
    {
        $user = $request->user();
        abort_unless($expense->user_id === $user->id || $this->isOffice($user), 403);
        abort_unless($expense->receipt_path && Storage::disk('local')->exists($expense->receipt_path), 404);

        return Storage::disk('local')->response($expense->receipt_path, null, ['Content-Type' => $expense->receipt_mime, 'Cache-Control' => 'private, max-age=86400']);
    }

    // ----------------------------------------------------------------

    private function isOffice(User $u): bool
    {
        return $u->isAdmin() || $u->hasRole(Role::Dispecer) || $u->hasRole(Role::Ekonom) || $u->hasRole(Role::Spolecnik);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';

        return $request->validate([
            'uuid' => [$create ? 'required' : 'prohibited', 'uuid'],
            'category' => [$req, Rule::in(Expense::CATEGORIES)],
            'amount' => [$req, 'numeric', 'min:0.01', 'max:999999'],
            'currency' => ['sometimes', Rule::in(['CZK', 'EUR', 'PLN'])],
            'spent_on' => [$req, 'date', 'before_or_equal:today'],
            'liters' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'paid_with' => ['sometimes', Rule::in(['own', 'company'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'ticket_id' => ['nullable', 'integer', 'exists:tickets,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'receipt' => [$create ? 'required' : 'nullable', 'file', 'max:'.config('pap.upload_max_kb'), 'mimes:jpg,jpeg,png,webp,heic,heif,pdf'],
        ]);
    }

    /** Zakázka určí projekt; technik smí jen svou zakázku */
    private function withTicket(array $data, User $user): array
    {
        if (! empty($data['ticket_id'])) {
            $t = Ticket::findOrFail($data['ticket_id']);
            abort_unless($t->assigned_technician_id === $user->id || $t->isVisibleTo($user), 403);
            $data['project_id'] = $t->project_id;
        }
        unset($data['receipt'], $data['uuid']);

        return $data;
    }

    private function storeReceipt(Request $request, Expense $e): void
    {
        if (! $request->hasFile('receipt')) {
            return;
        }
        $file = $request->file('receipt');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $old = $e->receipt_path;
        $e->receipt_path = $file->storeAs('expenses/'.now()->format('Y/m'), ($e->uuid ?: Str::uuid()).'-'.time().'.'.$ext, 'local');
        $e->receipt_mime = $file->getMimeType();
        if ($old) {
            Storage::disk('local')->delete($old);
        }
    }

    private const CAT = ['fuel' => 'PHM', 'parts' => 'Díly', 'material' => 'Materiál', 'parking' => 'Parkování', 'toll' => 'Mýto', 'lodging' => 'Ubytování', 'tools' => 'Nářadí', 'other' => 'Jiné'];

    private function line(Expense $e): string
    {
        return (self::CAT[$e->category] ?? $e->category).' · '.number_format($e->amount, 2, ',', ' ').' '.$e->currency;
    }

    private function push(?User $u, string $title, string $body): void
    {
        if ($u) {
            SendPushNotification::dispatch($u->id, ['title' => $title, 'body' => mb_substr($body, 0, 180), 'url' => '/technik/#/expenses', 'tag' => 'expenses']);
        }
    }

    private function notifyOffice(string $role, string $title, string $body): void
    {
        User::where('is_active', true)->whereHas('roleRows', fn ($q) => $q->where('role', $role))->get()
            ->each(fn ($u) => SendPushNotification::dispatch($u->id, ['title' => $title, 'body' => $body, 'url' => '/dispecink/#/expenses', 'tag' => 'expenses']));
    }

    /** @return array<string, mixed> */
    private function payload(Expense $e): array
    {
        return [
            'id' => $e->id,
            'uuid' => $e->uuid,
            'user' => $e->relationLoaded('user') && $e->user ? ['id' => $e->user->id, 'name' => $e->user->name] : ['id' => $e->user_id],
            'project' => $e->relationLoaded('project') && $e->project ? ['id' => $e->project->id, 'name' => $e->project->name] : null,
            'ticket' => $e->relationLoaded('ticket') && $e->ticket ? ['id' => $e->ticket->id, 'number' => $e->ticket->number] : null,
            'category' => $e->category,
            'amount' => $e->amount,
            'currency' => $e->currency,
            'spent_on' => $e->spent_on?->toDateString(),
            'liters' => $e->liters,
            'paid_with' => $e->paid_with,
            'description' => $e->description,
            'has_receipt' => (bool) $e->receipt_path,
            'receipt_mime' => $e->receipt_mime,
            'status' => $e->status,
            'return_note' => $e->return_note,
            'checked' => $e->checked_at ? ['by' => $e->relationLoaded('checker') ? $e->checker?->name : null, 'at' => $e->checked_at->toIso8601String()] : null,
            'approved' => $e->approved_at ? ['by' => $e->relationLoaded('approver') ? $e->approver?->name : null, 'at' => $e->approved_at->toIso8601String()] : null,
            'paid_at' => $e->paid_at?->toIso8601String(),
            'payout_id' => $e->payout_id,
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }
}
