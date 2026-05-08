<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OrderStatusService
{
    /**
     * @return array<string, array<int, string>>
     */
    public function transitions(): array
    {
        return [
            'draft' => ['validation_failed', 'action_needed', 'verified', 'cancelled'],
            'validation_failed' => ['action_needed', 'verified', 'cancelled'],
            'action_needed' => ['verified', 'cancelled'],
            'verified' => ['submitted', 'action_needed', 'cancelled'],
            'submitted' => ['in_production', 'action_needed', 'cancelled'],
            'in_production' => ['shipped', 'action_needed', 'cancelled'],
            'shipped' => ['closed'],
            'closed' => [],
            'cancelled' => [],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function allowedNextStatuses(Order $order): array
    {
        return $this->transitions()[$order->status] ?? [];
    }

    public function transition(Order $order, string $toStatus, User $user, ?string $note = null, array $metadata = []): OrderStatusEvent
    {
        $fromStatus = $order->status;

        if (! in_array($toStatus, $this->allowedNextStatuses($order), true)) {
            throw ValidationException::withMessages([
                'status' => ["Order cannot move from {$fromStatus} to {$toStatus}."],
            ]);
        }

        $timestamps = match ($toStatus) {
            'submitted' => ['submitted_at' => $order->submitted_at ?? now()],
            'shipped' => [
                'shipped_at' => now(),
                'tracking_number' => $metadata['tracking_number'] ?? $order->tracking_number,
                'tracking_url' => $metadata['tracking_url'] ?? $order->tracking_url,
            ],
            default => [],
        };

        $order->update([
            'status' => $toStatus,
            ...$timestamps,
        ]);

        $event = OrderStatusEvent::query()->create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'user_id' => $user->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'metadata' => $metadata,
        ]);

        AuditLog::query()->create([
            'tenant_id' => $order->tenant_id,
            'user_id' => $user->id,
            'event' => 'order.status_changed',
            'auditable_type' => Order::class,
            'auditable_id' => $order->id,
            'metadata' => [
                'order_number' => $order->order_number,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'note' => $note,
            ],
        ]);

        return $event;
    }
}
