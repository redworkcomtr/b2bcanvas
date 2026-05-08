<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'order_id', 'status', 'type', 'title', 'description', 'payload', 'last_activity_at', 'resolved_at'])]
class RequiredAction extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_activity_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
