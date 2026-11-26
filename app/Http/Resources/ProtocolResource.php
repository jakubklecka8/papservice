<?php

namespace App\Http\Resources;

use App\Models\Protocol;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Protocol */
class ProtocolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'visit_uuid' => $this->whenLoaded('visit', fn () => $this->visit->uuid),
            'language' => $this->language,
            'status' => $this->status,
            'content' => $this->data,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'template' => $this->data['template'] ?? 'standard',
            'html_url' => url('/api/v1/protocols/'.$this->id.'/html'),
            'pdf_url' => url('/api/v1/protocols/'.$this->id.'/pdf'),
        ];
    }
}
