<?php

namespace App\Services;

use App\Events\NotificationRequested;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Models\User;
use App\Support\NotificationEventCatalog;
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

    public function transition(Order $order, string $toStatus, ?User $user = null, ?string $note = null, array $metadata = []): OrderStatusEvent
    {
        $fromStatus = $order->status;

        if (! in_array($toStatus, $this->allowedNextStatuses($order), true)) {
            throw ValidationException::withMessages([
                'status' => ["Order cannot move from {$fromStatus} to {$toStatus}."],
            ]);
        }

        if ($toStatus === 'submitted' && ! $this->isPaymentAllowed($order)) {
            throw ValidationException::withMessages([
                'status' => ['Order must be paid before it can be submitted.'],
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
            'user_id' => $user?->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'metadata' => $metadata,
        ]);

        $this->dispatchNotification($order, $user, $toStatus, $metadata, $note, $fromStatus);

        AuditLog::query()->create([
            'tenant_id' => $order->tenant_id,
            'user_id' => $user?->id,
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

    private function isPaymentAllowed(Order $order): bool
    {
        if (in_array($order->payment_status, ['paid', 'not_required'], true)) {
            return true;
        }

        if (($order->totals['subtotal_cents'] ?? 0) <= 0) {
            return true;
        }

        return false;
    }

    private function dispatchNotification(Order $order, ?User $user, string $toStatus, array $metadata, ?string $note = null, string $fromStatus = 'draft'): void
    {
        $eventMap = match ($toStatus) {
            'shipped' => NotificationEventCatalog::ORDER_SHIPPED,
            'action_needed' => NotificationEventCatalog::ORDER_ACTION_NEEDED,
            'validation_failed' => NotificationEventCatalog::ORDER_VALIDATION_FAILED,
            default => null,
        };

        if (! $eventMap) {
            return;
        }

        NotificationRequested::dispatch(
            $eventMap,
            $order->tenant_id,
            [
                'order_number' => $order->order_number,
                'order_id' => $order->id,
                'to_status' => $toStatus,
                'from_status' => $fromStatus,
                'tracking_number' => $metadata['tracking_number'] ?? $order->tracking_number,
                'tracking_url' => $metadata['tracking_url'] ?? $order->tracking_url,
                'note' => $note ?? null,
            ],
            $user,
        );
    }
}
