<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayRate extends Model
{
    protected $fillable = ['user_id', 'project_id', 'service_type', 'currency', 'per_visit', 'per_km', 'per_hour', 'note'];

    protected function casts(): array
    {
        return ['per_visit' => 'float', 'per_km' => 'float', 'per_hour' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Nejkonkrétnější sazba: technik + projekt + typ > … > obecná. Řádky předané z cache. */
    public static function pick(iterable $rates, int $userId, int $projectId, ?string $serviceType): ?self
    {
        $best = null;
        $bestScore = -1;
        foreach ($rates as $r) {
            if (($r->user_id && $r->user_id !== $userId) || ($r->project_id && $r->project_id !== $projectId)
                || ($r->service_type && $r->service_type !== $serviceType)) {
                continue;
            }
            $score = ($r->user_id ? 4 : 0) + ($r->project_id ? 2 : 0) + ($r->service_type ? 1 : 0);
            if ($score > $bestScore) {
                [$best, $bestScore] = [$r, $score];
            }
        }

        return $best;
    }
}
