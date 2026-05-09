<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['tenant_id', 'order_id', 'assigned_to_id', 'type', 'status', 'priority', 'request_type', 'reasons', 'description', 'contact', 'total_notes_count', 'unread_notes_count', 'last_activity_at', 'last_read_at', 'resolved_at', 'closed_at'])]
class Issue extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'reasons' => 'array',
            'contact' => 'array',
            'last_activity_at' => 'datetime',
            'last_read_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
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
        return $this->hasMany(IssueComment::class);
    }

    public function claimResolution(): HasOne
    {
        return $this->hasOne(ClaimResolution::class)->latestOfMany();
    }

    public function claimResolutions(): HasMany
    {
        return $this->hasMany(ClaimResolution::class);
    }
}
