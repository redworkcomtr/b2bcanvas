<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'subscription_id',
    'event',
    'recipient_email',
    'subject',
    'body_html',
    'body_text',
    'status',
    'attempts',
    'max_attempts',
    'message_id',
    'metadata',
    'error_message',
    'sent_at',
])]
class NotificationMailLog extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(NotificationSubscription::class, 'subscription_id');
    }
}
