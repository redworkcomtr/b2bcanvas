<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['import_id', 'row_number', 'status', 'payload', 'errors'])]
class ImportRow extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'errors' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
