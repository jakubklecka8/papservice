<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'project_id', 'end_customer_id', 'location_id', 'service_type', 'priority', 'status', 'source', 'needs_review',
        'title', 'description', 'created_by', 'reporter_name', 'reporter_email', 'reporter_phone',
        'assigned_technician_id', 'assigned_by', 'assigned_at', 'due_at', 'closed_at', 'legacy_id', 'legacy_status',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'needs_review' => 'boolean',
            'assigned_at' => 'datetime',
            'due_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            $ticket->uuid ??= (string) Str::uuid();
        });

        // Číslo se přidělí až po úspěšném uložení (potvrzení e-mailem se posílá až po něm – 3.3)
        static::created(function (Ticket $ticket) {
            if ($ticket->number === null) {
                $ticket->number = sprintf('PAP-%s-%06d', $ticket->created_at->format('Y'), $ticket->id);
                $ticket->saveQuietly();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function endCustomer(): BelongsTo
    {
        return $this->belongsTo(EndCustomer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** E-mail, ze kterého zakázka vznikla */
    public function sourceEmail(): HasOne
    {
        return $this->hasOne(InboundEmail::class)->whereIn('status', ['ticket', 'review'])->oldest('id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function protocols(): HasMany
    {
        return $this->hasMany(Protocol::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class)->orderBy('id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Jediné místo, které určuje, které zakázky uživatel vidí.
     * Používá se v seznamech, v policy i při stahování příloh.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canReadAllProjects()) {
            return $query;
        }

        $projectIds = $user->dispatcherProjectIds();
        $locationIds = $user->customerLocationIds();

        return $query->where(function (Builder $q) use ($user, $projectIds, $locationIds) {
            $q->where('assigned_technician_id', $user->id);
            if ($projectIds) {
                $q->orWhereIn('project_id', $projectIds);
            }
            if ($locationIds) {
                $q->orWhereIn('location_id', $locationIds);
            }
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

    public function changeStatus(TicketStatus $to, ?User $by = null, ?string $note = null): void
    {
        $from = $this->status;
        if ($from === $to) {
            return;
        }

        $this->status = $to;
        if ($to === TicketStatus::Closed || $to === TicketStatus::Cancelled) {
            $this->closed_at ??= now();
        }
        $this->save();

        $this->history()->create([
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'user_id' => $by?->id,
            'note' => $note,
        ]);
    }
}
