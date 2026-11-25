<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Attachment */
class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'kind' => $this->kind,
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'caption' => $this->caption,
            'customer_visible' => $this->customer_visible,
            'created_at' => $this->created_at?->toIso8601String(),
            'download_url' => url('/api/v1/attachments/'.$this->uuid.'/download'),
        ];
    }
}
