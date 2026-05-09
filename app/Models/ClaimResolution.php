<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['issue_id', 'user_id', 'decision', 'amount_cents', 'currency', 'finance_reference', 'production_outcome', 'notes', 'evidence_files'])]
class ClaimResolution extends Model
{
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'evidence_files' => 'array',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
