<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProtocolResource;
use App\Models\Protocol;
use App\Models\Visit;
use App\Services\ProtocolBuilder;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProtocolController extends Controller
{
    public function __construct(private readonly ProtocolBuilder $builder) {}

    /** Dispečer vytvoří návrh protokolu z odeslané návštěvy (CZ nebo PL) */
    public function store(Request $request, Visit $visit): JsonResponse
    {
        $this->authorize('manage', $visit->ticket);
        $data = $request->validate(['language' => ['required', Rule::in(['cs', 'pl'])]]);
        abort_unless($visit->status === VisitStatus::Submitted, 422, 'Protokol lze vytvořit jen z odeslané návštěvy.');

        $protocol = DB::transaction(function () use ($visit, $data, $request) {
            Protocol::where('visit_id', $visit->id)->where('status', 'draft')->delete();

            return Protocol::create([
                'ticket_id' => $visit->ticket_id,
                'visit_id' => $visit->id,
                'language' => $data['language'],
                'status' => 'draft',
                'data' => $this->builder->build($visit, $data['language']),
                'created_by' => $request->user()->id,
            ]);
        });

        return (new ProtocolResource($protocol->load('visit')))->response()->setStatusCode(201);
    }

    /**
     * Schválení. Před schválením se ověří, že protokol pořád odpovídá datům
     * v zakázce – pokud se mezitím něco změnilo, musí se vygenerovat znovu (2.2).
     */
    public function approve(Request $request, Protocol $protocol): JsonResponse
    {
        $visit = $protocol->visit;
        $this->authorize('manage', $protocol->ticket);
        abort_unless($protocol->status === 'draft', 422, 'Protokol už je schválený.');
        abort_unless($visit->status === VisitStatus::Submitted, 422, 'Návštěva není ve stavu odeslaná.');

        $fresh = $this->builder->build($visit->fresh(), $protocol->language);
        if ($fresh != $protocol->data) {
            return response()->json(['message' => 'Protokol neodpovídá aktuálním datům návštěvy. Vytvořte ho znovu.'], 409);
        }

        DB::transaction(function () use ($protocol, $visit, $request) {
            $protocol->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $visit->update(['status' => VisitStatus::Approved]);
            $protocol->ticket->changeStatus(TicketStatus::Closed, $request->user(), 'Protokol schválen ('.strtoupper($protocol->language).')');
        });

        return (new ProtocolResource($protocol->fresh()->load('visit')))->response();
    }

    public function show(Request $request, Protocol $protocol): ProtocolResource
    {
        $this->authorizeView($request, $protocol);

        return new ProtocolResource($protocol->load('visit'));
    }

    /** Tisknutelná verze (v prohlížeči Uložit jako PDF) */
    public function html(Request $request, Protocol $protocol): Response
    {
        $this->authorizeView($request, $protocol);

        return response()->view(self::viewName($protocol), self::viewData($protocol));
    }

    /**
     * PDF vygenerované na serveru (dompdf, font DejaVu – česká i polská diakritika).
     * Schválený protokol se uloží do storage a příště se vrací uložený soubor.
     */
    public function pdf(Request $request, Protocol $protocol): Response
    {
        $this->authorizeView($request, $protocol);

        $path = 'protocols/'.$protocol->id.'.pdf';
        $disk = Storage::disk('local');
        if ($protocol->isApproved() && $disk->exists($path)) {
            $pdf = $disk->get($path);
        } else {
            $pdf = $this->renderPdf($protocol);
            if ($protocol->isApproved()) {
                $disk->put($path, $pdf);
            }
        }

        $name = 'protokol_'.preg_replace('/[^A-Za-z0-9_-]+/', '_', $protocol->data['ticket']['number'] ?? (string) $protocol->id).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }

    public static function renderPdf(Protocol $protocol): string
    {
        $html = view(self::viewName($protocol), self::viewData($protocol))->render();
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('tempDir', storage_path('framework/cache'));
        $options->set('fontCache', storage_path('framework/cache'));
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    private static function viewName(Protocol $protocol): string
    {
        return ($protocol->data['template'] ?? 'standard') === 'm4b' && ! empty($protocol->data['m4b_labels'])
            ? 'protocols.m4b' : 'protocols.show';
    }

    /** @return array<string, mixed> */
    private static function viewData(Protocol $protocol): array
    {
        return ['protocol' => $protocol, 'd' => $protocol->data, 'l' => $protocol->data['labels']];
    }

    private function authorizeView(Request $request, Protocol $protocol): void
    {
        $user = $request->user();
        abort_unless($protocol->ticket && $protocol->ticket->isVisibleTo($user), 403);
        abort_if($user->isCustomerOnly() && ! $protocol->isApproved(), 403);
    }
}
