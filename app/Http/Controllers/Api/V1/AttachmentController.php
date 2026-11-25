<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Přílohy se ukládají mimo veřejný adresář (storage/app/private) a stahují
 * se jen přes tento kontroler s kontrolou oprávnění. Přímý odkaz na soubor neexistuje.
 */
class AttachmentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'uuid' => ['required', 'uuid'],
            'attachable_type' => ['required', Rule::in(['ticket', 'visit'])],
            'attachable_id' => ['required', 'max:64'],    // id zakázky nebo UUID návštěvy
            'file' => ['required', 'file', 'max:'.config('pap.upload_max_kb'), 'mimes:jpg,jpeg,png,webp,heic,heif,pdf'],
            'kind' => ['nullable', Rule::in(['photo', 'document'])],
            'caption' => ['nullable', 'string', 'max:255'],
            'customer_visible' => ['nullable', 'boolean'],
        ]);
        $uuid = strtolower($data['uuid']);

        $parent = $data['attachable_type'] === 'ticket'
            ? Ticket::findOrFail($data['attachable_id'])
            : Visit::where('uuid', strtolower((string) $data['attachable_id']))->firstOrFail();
        $ticket = $parent instanceof Ticket ? $parent : $parent->ticket;

        if ($parent instanceof Visit) {
            $mayUpload = ($parent->technician_id === $user->id && $parent->isEditable() && $user->can('work', $ticket))
                || $user->can('manage', $ticket);
        } else {
            $mayUpload = $user->can('contribute', $ticket);
        }
        abort_unless($mayUpload, 403);

        // Idempotence: stejné UUID z offline fronty = stejná příloha
        if ($existing = Attachment::where('uuid', $uuid)->first()) {
            abort_unless($existing->uploaded_by === $user->id, 409, 'UUID už je použité.');

            return (new AttachmentResource($existing))->response()->setStatusCode(200);
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = $file->storeAs('attachments/'.now()->format('Y/m'), $uuid.'.'.$ext, 'local');

        $attachment = $parent->attachments()->create([
            'uuid' => $uuid,
            'uploaded_by' => $user->id,
            'kind' => $data['kind'] ?? (str_starts_with((string) $file->getMimeType(), 'image/') ? 'photo' : 'document'),
            'disk' => 'local',
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'caption' => $data['caption'] ?? null,
            // označit pro zákazníka smí jen dispečer/admin; zákazník vidí své vlastní nahrané vždy
            'customer_visible' => $user->can('manage', $ticket) ? (bool) ($data['customer_visible'] ?? false) : false,
        ]);

        return (new AttachmentResource($attachment))->response()->setStatusCode(201);
    }

    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_name ?? basename($attachment->path),
            ['Cache-Control' => 'private, max-age=3600'],
        );
    }

    /** Dispečer vybere fotky pro zákazníka / upraví popisek */
    public function update(Request $request, Attachment $attachment): AttachmentResource
    {
        $this->authorize('update', $attachment);
        $data = $request->validate([
            'customer_visible' => ['sometimes', 'boolean'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);
        $attachment->update($data);

        return new AttachmentResource($attachment);
    }
}
