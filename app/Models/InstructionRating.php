<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructionRating extends Model
{
    protected $fillable = ['instruction_version_id', 'user_id', 'visit_id', 'helpful', 'comment'];

    protected function casts(): array
    {
        return ['helpful' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(InstructionVersion::class, 'instruction_version_id');
    }
}
