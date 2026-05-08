<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'user_id', 'event', 'auditable_type', 'auditable_id', 'metadata'])]
class AuditLog extends Model
{
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
