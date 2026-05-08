<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'filename', 'status', 'total_rows', 'valid_rows', 'invalid_rows', 'summary'])]
class Import extends Model
{
    protected function casts(): array
    {
        return ['summary' => 'array'];
    }
}
