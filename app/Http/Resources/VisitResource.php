<?php

namespace App\Http\Resources;

use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Visit */
class VisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'ticket_id' => $this->ticket_id,
            'technician' => $this->whenLoaded('technician', fn () => ['id' => $this->technician->id, 'name' => $this->technician->name]),
            'status' => $this->status->value,
            'arrived_at' => $this->arrived_at?->toIso8601String(),
            'departed_at' => $this->departed_at?->toIso8601String(),
            'minutes_on_site' => $this->minutes_on_site,
            'km' => $this->km,
            'work_description' => $this->work_description,
            'repair_description' => $this->repair_description,
            'test_result' => $this->test_result?->value,
            'test_note' => $this->test_note,
            'instruction_ref' => $this->instruction_ref,
            'signer_name' => $this->signer_name,
            'signature_technician' => $this->signature_technician,
            'signature_customer' => $this->signature_customer,
            'signed_at' => $this->signed_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'return_note' => $this->return_note,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'parts' => $this->whenLoaded('parts', fn () => $this->parts->map(fn ($p) => [
                'uuid' => $p->uuid,
                'part_id' => $p->part_id,
                'serialized' => (bool) ($p->relationLoaded('part') ? $p->part?->serialized : false),
                // u dílů se sériovými čísly označit chybějící S/N (4.2)
                'missing_serial_removed' => (bool) ($p->relationLoaded('part') && $p->part?->serialized && blank($p->serial_removed)),
                'missing_serial_installed' => (bool) ($p->relationLoaded('part') && $p->part?->serialized && blank($p->serial_installed)),
                'name' => $p->name,
                'part_number' => $p->part_number,
                'quantity' => $p->quantity,
                'serial_removed' => $p->serial_removed,
                'serial_installed' => $p->serial_installed,
                'note' => $p->note,
            ])),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
