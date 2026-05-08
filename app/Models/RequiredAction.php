<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'order_id', 'assigned_to_id', 'status', 'priority', 'type', 'title', 'description', 'payload', 'resolution_payload', 'last_activity_at', 'resolved_at', 'escalated_at'])]
class RequiredAction extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'resolution_payload' => 'array',
            'last_activity_at' => 'datetime',
            'resolved_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RequiredActionComment::class);
    }
}
