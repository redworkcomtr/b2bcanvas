<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'order_id', 'type', 'status', 'request_type', 'reasons', 'description', 'contact', 'total_notes_count', 'unread_notes_count', 'last_activity_at'])]
class Issue extends Model
{
    protected function casts(): array
    {
        return [
            'reasons' => 'array',
            'contact' => 'array',
            'last_activity_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IssueComment::class);
    }
}
