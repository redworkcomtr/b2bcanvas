<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class NotificationRequested
{
    use Dispatchable;

    public function __construct(
        public string $event,
        public int $tenantId,
        public array $payload = [],
        public ?User $actor = null,
    ) {}
}
