<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstructionVersion extends Model
{
    protected $fillable = ['instruction_id', 'version', 'status', 'symptom', 'steps', 'note', 'change_note', 'created_by', 'published_by', 'published_at'];

    protected function casts(): array
    {
        return ['steps' => 'array', 'published_at' => 'datetime', 'version' => 'integer'];
    }

    public function instruction(): BelongsTo
    {
        return $this->belongsTo(Instruction::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(InstructionRating::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
