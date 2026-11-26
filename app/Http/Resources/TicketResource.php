<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Zakázka. Pro uživatele, který je jen zákazník, se skryjí interní údaje:
 * návštěvy, neschválené protokoly a fotky neoznačené pro zákazníka.
 *
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $customerOnly = $user?->isCustomerOnly() ?? true;
        $locale = $user?->locale ?? 'cs';

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'number' => $this->number,
            'external_ref' => $this->external_ref,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label($locale),
            'service_type' => $this->service_type,
            'priority' => $this->priority,
            'source' => $this->source,
            'needs_review' => (bool) $this->needs_review,
            'email' => $this->when(! $customerOnly && $this->relationLoaded('sourceEmail') && $this->sourceEmail, fn () => [
                'id' => $this->sourceEmail->id,
                'from' => trim(($this->sourceEmail->from_name ? $this->sourceEmail->from_name.' ' : '').'<'.$this->sourceEmail->from_email.'>'),
                'subject' => $this->sourceEmail->subject,
                'received_at' => $this->sourceEmail->received_at?->toIso8601String(),
                'confirmation_sent_at' => $this->sourceEmail->confirmation_sent_at?->toIso8601String(),
                'ai' => collect($this->sourceEmail->ai_result ?? [])->only(['engine', 'confidence', 'reason', 'candidates', 'location_hints'])->all(),
            ]),
            'project' => $this->whenLoaded('project', fn () => ['id' => $this->project->id, 'code' => $this->project->code, 'name' => $this->project->name, 'protocol_template' => $this->project->protocol_template ?? 'standard']),
            'location' => $this->whenLoaded('location', fn () => $this->location ? [
                'id' => $this->location->id,
                'code' => $this->location->code,
                'name' => $this->location->name,
                'street' => $this->location->street,
                'city' => $this->location->city,
                'zip' => $this->location->zip,
                'country' => $this->location->country,
                'lat' => $this->location->lat,
                'lng' => $this->location->lng,
            ] : null),
            'technician' => $this->whenLoaded('technician', fn () => $this->technician ? ['id' => $this->technician->id, 'name' => $this->technician->name] : null),
            'reporter' => $this->when(! $customerOnly, fn () => [
                'name' => $this->reporter_name,
                'email' => $this->reporter_email,
                'phone' => $this->reporter_phone,
            ]),
            'due_at' => $this->due_at?->toIso8601String(),
            'planned_at' => $this->planned_at?->toIso8601String(),
            'ai_advice' => $this->when(! $customerOnly && $this->ai_advice, fn () => $this->ai_advice + ['at' => $this->ai_advice_at?->toIso8601String()]),
            'device' => $this->when(! $customerOnly && $this->relationLoaded('device') && $this->device, fn () => [
                'id' => $this->device->id, 'type' => $this->device->type, 'model' => $this->device->model,
                'serial_number' => $this->device->serial_number, 'installed_at' => $this->device->installed_at?->toDateString(),
            ]),
            'checklist' => $this->when(! $customerOnly && $this->relationLoaded('checklistTemplate') && $this->checklistTemplate, fn () => [
                'id' => $this->checklistTemplate->id, 'name' => $this->checklistTemplate->name, 'items' => $this->checklistTemplate->items,
            ]),
            'schedule' => $this->when(! $customerOnly && $this->relationLoaded('schedule') && $this->schedule, fn () => [
                'id' => $this->schedule->id, 'title' => $this->schedule->title, 'interval_months' => $this->schedule->interval_months,
            ]),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'client_report_sent_at' => $this->when(! $customerOnly, fn () => $this->client_report_sent_at?->toIso8601String()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // cena jen pro dispečera projektu, admina, ekonoma, společníka – ne technik ani zákazník
            'price' => $this->when(
                $this->relationLoaded('visits') && $user && ($user->canReadAllProjects() || $user->can('manage', $this->resource)),
                fn () => app(PricingService::class)->quote($this->resource),
            ),
            'visits' => $this->when(! $customerOnly && $this->relationLoaded('visits'), fn () => VisitResource::collection($this->visits)),
            'history' => $this->when(! $customerOnly && $this->relationLoaded('history'), fn () => $this->history->map(fn ($h) => [
                'from' => $h->from_status,
                'to' => $h->to_status,
                'user' => $h->user?->name,
                'note' => $h->note,
                'at' => $h->created_at?->toIso8601String(),
            ])),
            'protocols' => $this->whenLoaded('protocols', fn () => ProtocolResource::collection(
                $customerOnly ? $this->protocols->where('status', 'approved')->values() : $this->protocols
            )),
            'attachments' => $this->whenLoaded('attachments', fn () => AttachmentResource::collection($this->visibleAttachments($customerOnly, $user?->id))),
        ];
    }

    /** Přílohy zakázky + fotky z návštěv; zákazník jen označené nebo vlastní */
    private function visibleAttachments(bool $customerOnly, ?int $userId)
    {
        $all = $this->attachments;
        if ($this->relationLoaded('visits')) {
            foreach ($this->visits as $visit) {
                if ($visit->relationLoaded('attachments')) {
                    $all = $all->concat($visit->attachments);
                }
            }
        }

        if ($customerOnly) {
            $all = $all->filter(fn ($a) => $a->customer_visible || $a->uploaded_by === $userId);
        }

        return $all->sortBy('id')->values();
    }
}
