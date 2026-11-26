<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\InboundEmail;
use App\Models\Mailbox;
use App\Models\Setting;
use App\Models\User;
use App\Services\Mail\EmailClassifier;
use App\Services\Mail\InboundEmailProcessor;
use App\Services\Mail\MailboxImporter;
use App\Services\Mail\MailboxReader;
use App\Services\Mail\RitmParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;

/** Schránky (admin), nastavení AI (admin) a přehled příchozích e-mailů (dispečer) */
class MailController extends Controller
{
    // ------------------------------------------------------------ schránky

    public function mailboxes(Request $request): JsonResponse
    {
        $this->ensureAdmin($request->user());

        return response()->json(['data' => Mailbox::with('project:id,name')->orderBy('name')->get()->map(fn (Mailbox $m) => $this->mbPayload($m))]);
    }

    public function storeMailbox(Request $request): JsonResponse
    {
        $this->ensureAdmin($request->user());
        $data = $this->validateMailbox($request, null);
        $data['import_since'] = now()->subDay(); // starší pošta se nenačítá

        return response()->json(['data' => $this->mbPayload(Mailbox::create($data))], 201);
    }

    public function updateMailbox(Request $request, Mailbox $mailbox): JsonResponse
    {
        $this->ensureAdmin($request->user());
        $data = $this->validateMailbox($request, $mailbox);
        // prázdné heslo = beze změny
        foreach (['imap_password', 'smtp_password'] as $k) {
            if (array_key_exists($k, $data) && blank($data[$k])) {
                unset($data[$k]);
            }
        }
        $mailbox->update($data);

        return response()->json(['data' => $this->mbPayload($mailbox->fresh('project'))]);
    }

    public function destroyMailbox(Request $request, Mailbox $mailbox): JsonResponse
    {
        $this->ensureAdmin($request->user());
        $mailbox->update(['is_active' => false]); // historie e-mailů zůstává

        return response()->json(['ok' => true]);
    }

    /** Zkusí se přihlásit do IMAP (a ověří SMTP přihlášení) */
    public function testMailbox(Request $request, Mailbox $mailbox, MailboxReader $reader): JsonResponse
    {
        $this->ensureAdmin($request->user());
        $out = ['imap' => null, 'smtp' => null];

        try {
            $out['imap'] = ['ok' => true, 'messages' => $reader->test($mailbox)];
        } catch (Throwable $e) {
            $out['imap'] = ['ok' => false, 'error' => Str::limit($e->getMessage(), 300)];
        }

        if ($mailbox->canSend()) {
            try {
                $transport = (new EsmtpTransport(
                    $mailbox->smtp_host, $mailbox->smtp_port ?: 465, $mailbox->smtp_encryption === 'ssl',
                ));
                $transport->setUsername($mailbox->smtp_username);
                $transport->setPassword($mailbox->smtp_password);
                $transport->start();
                $transport->stop();
                $out['smtp'] = ['ok' => true];
            } catch (Throwable $e) {
                $out['smtp'] = ['ok' => false, 'error' => Str::limit($e->getMessage(), 300)];
            }
        }

        return response()->json($out);
    }

    /** Načíst schránku hned (bez čekání na cron) */
    public function fetchNow(Request $request, Mailbox $mailbox, MailboxImporter $importer): JsonResponse
    {
        $this->ensureAdmin($request->user());
        $n = $importer->import($mailbox);

        return response()->json(['ok' => $mailbox->last_error === null, 'new' => $n, 'error' => $mailbox->last_error]);
    }

    private function validateMailbox(Request $request, ?Mailbox $mb): array
    {
        $req = $mb ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$req, 'string', 'max:255'],
            'project_id' => [$req, 'integer', 'exists:projects,id'],
            'parser' => ['sometimes', Rule::in(['ai', 'zasilkovna_ritm'])],
            'imap_host' => [$req, 'string', 'max:255'],
            'imap_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'imap_encryption' => ['nullable', Rule::in(['ssl', 'tls', 'none'])],
            'imap_username' => [$req, 'string', 'max:255'],
            'imap_password' => [$mb ? 'nullable' : 'required', 'string', 'max:500'],
            'imap_folder' => ['nullable', 'string', 'max:255'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', Rule::in(['ssl', 'tls', 'none'])],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:500'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'dedicated' => ['sometimes', 'boolean'],
            'auto_reply' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function mbPayload(Mailbox $m): array
    {
        return $m->only([
            'id', 'name', 'project_id', 'parser', 'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_folder',
            'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'from_address', 'from_name',
            'dedicated', 'auto_reply', 'is_active', 'last_checked_at', 'last_error',
        ]) + [
            'project' => $m->project?->only(['id', 'name']),
            'has_imap_password' => filled($m->getRawOriginal('imap_password')),
            'has_smtp_password' => filled($m->getRawOriginal('smtp_password')),
            'emails_count' => $m->emails()->count(),
        ];
    }

    // ------------------------------------------------------------ AI

    public function aiSettings(Request $request): JsonResponse
    {
        $this->ensureAdmin($request->user());
        $key = Setting::getSecret('anthropic_api_key');

        return response()->json([
            'has_key' => filled($key),
            'key_hint' => $key ? '…'.substr($key, -4) : null,
            'model' => Setting::get('anthropic_model') ?: EmailClassifier::DEFAULT_MODEL,
        ]);
    }

    public function saveAiSettings(Request $request): JsonResponse
    {
        $this->ensureAdmin($request->user());
        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'max:300'],
            'remove_key' => ['sometimes', 'boolean'],
            'model' => ['nullable', 'string', 'max:100'],
        ]);

        if (! empty($data['remove_key'])) {
            Setting::putSecret('anthropic_api_key', null);
        } elseif (filled($data['api_key'] ?? null)) {
            Setting::putSecret('anthropic_api_key', trim($data['api_key']));
        }
        if (array_key_exists('model', $data)) {
            Setting::put('anthropic_model', $data['model'] ?: null);
        }

        return $this->aiSettings($request);
    }

    /** Ověří API klíč krátkým dotazem */
    public function testAi(Request $request): JsonResponse
    {
        $this->ensureAdmin($request->user());
        $key = Setting::getSecret('anthropic_api_key');
        abort_unless($key, 422, 'API klíč není nastavený.');

        try {
            $res = Http::timeout(30)->withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => Setting::get('anthropic_model') ?: EmailClassifier::DEFAULT_MODEL,
                    'max_tokens' => 20,
                    'messages' => [['role' => 'user', 'content' => 'Odpověz jedním slovem: funguješ?']],
                ]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => Str::limit($e->getMessage(), 300)]);
        }

        return response()->json($res->successful()
            ? ['ok' => true, 'reply' => collect($res->json('content'))->firstWhere('type', 'text')['text'] ?? '']
            : ['ok' => false, 'error' => 'HTTP '.$res->status().': '.Str::limit($res->body(), 300)]);
    }

    // ------------------------------------------------------------ příchozí e-maily (dispečer)

    public function emails(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureDispatcher($user);
        $data = $request->validate([
            'status' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $q = InboundEmail::query()->with(['mailbox:id,name,project_id', 'ticket:id,number,status'])
            ->whereHas('mailbox', fn ($m) => $this->projectScope($m, $user))
            ->orderByDesc('id');
        if (! empty($data['status'])) {
            $q->whereIn('status', explode(',', $data['status']));
        }

        $page = $q->paginate($data['per_page'] ?? 50);
        $page->getCollection()->transform(fn (InboundEmail $e) => $this->emailPayload($e, false));

        return response()->json($page);
    }

    public function email(Request $request, InboundEmail $email): JsonResponse
    {
        $this->ensureEmailAccess($request->user(), $email);

        return response()->json(['data' => $this->emailPayload($email->load(['mailbox', 'ticket']), true)]);
    }

    /** Ručně: z e-mailu (ignorovaného / s chybou) udělat zakázku, nebo e-mail ignorovat / znovu zpracovat */
    public function emailAction(Request $request, InboundEmail $email): JsonResponse
    {
        $user = $request->user();
        $this->ensureEmailAccess($user, $email);
        $data = $request->validate(['action' => ['required', Rule::in(['ignore', 'reprocess'])]]);

        if ($data['action'] === 'ignore') {
            abort_if($email->ticket_id, 422, 'E-mail už má zakázku.');
            $email->update(['status' => 'ignored', 'processed_at' => now()]);
        } else {
            abort_if($email->ticket_id, 422, 'E-mail už má zakázku.');
            $email->update(['status' => 'new', 'error' => null]);
            // ručně spuštěné zpracování: i u nevyhrazené schránky zakázku založit;
            // když nejde přečíst jako RITM, zkusit obecné zpracování (AI / heuristika)
            $email->mailbox->dedicated = true;
            if ($email->mailbox->parser === 'zasilkovna_ritm' && $email->status === 'new' && $email->error === null
                && app(RitmParser::class)->parse((string) $email->body_text) === null) {
                $email->mailbox->parser = 'ai';
            }
            app(InboundEmailProcessor::class)->process($email->setRelation('mailbox', $email->mailbox));
        }

        return response()->json(['data' => $this->emailPayload($email->fresh(['mailbox', 'ticket']), true)]);
    }

    private function emailPayload(InboundEmail $e, bool $full): array
    {
        return [
            'id' => $e->id,
            'mailbox' => $e->mailbox?->only(['id', 'name', 'project_id']),
            'from_email' => $e->from_email,
            'from_name' => $e->from_name,
            'subject' => $e->subject,
            'received_at' => $e->received_at?->toIso8601String(),
            'status' => $e->status,
            'ticket' => $e->ticket?->only(['id', 'number']) + ($e->ticket ? ['status' => $e->ticket->status->value] : []),
            'attachments_count' => count($e->attachments ?? []),
            'confirmation_sent_at' => $e->confirmation_sent_at?->toIso8601String(),
            'error' => $e->error,
            'ai' => $e->ai_result ? collect($e->ai_result)->only(['engine', 'is_request', 'title', 'summary', 'confidence', 'reason', 'candidates', 'location_hints'])->all() : null,
        ] + ($full ? ['body_text' => $e->body_text, 'attachments' => collect($e->attachments ?? [])->map(fn ($a) => collect($a)->only(['name', 'mime', 'size']))] : []);
    }

    private function projectScope($mailboxQuery, User $user)
    {
        if ($user->isAdmin()) {
            return $mailboxQuery;
        }

        return $mailboxQuery->whereIn('project_id', $user->dispatcherProjectIds());
    }

    private function ensureEmailAccess(User $user, InboundEmail $email): void
    {
        $this->ensureDispatcher($user);
        abort_unless($user->isAdmin() || in_array($email->mailbox->project_id, $user->dispatcherProjectIds(), true), 403);
    }

    private function ensureAdmin(User $user): void
    {
        abort_unless($user->isAdmin(), 403);
    }

    private function ensureDispatcher(User $user): void
    {
        abort_unless($user->hasRole(Role::Admin, Role::Dispecer), 403);
    }
}
