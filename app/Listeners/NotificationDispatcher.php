<?php

namespace App\Listeners;

use App\Events\NotificationRequested;
use App\Jobs\SendNotificationMail;
use App\Models\NotificationMailLog;
use App\Models\NotificationSubscription;
use App\Services\NotificationTemplateService;

class NotificationDispatcher
{
    public function __construct(
        private readonly NotificationTemplateService $templates,
    ) {}

    public function handle(NotificationRequested $event): void
    {
        $subscriptions = NotificationSubscription::query()
            ->with('user')
            ->whereHas('user', fn ($query) => $query
                ->where('tenant_id', $event->tenantId)
                ->where('active', true),
            )
            ->where('event', $event->event)
            ->where('is_subscribed', true)
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $composition = $this->templates->compose($event->event, $event->payload);

        foreach ($subscriptions as $subscription) {
            $email = $subscription->email;
            $unsub = route('notifications.unsubscribe', ['token' => $subscription->ensureUnsubscribeToken()]);
            $payload = $this->templates->withUnsubscribe($composition, $subscription, $unsub);

            $log = NotificationMailLog::query()->create([
                'tenant_id' => $event->tenantId,
                'subscription_id' => $subscription->id,
                'event' => $event->event,
                'recipient_email' => $email,
                'subject' => $payload['subject'],
                'body_html' => $payload['body_html'],
                'body_text' => $payload['body_text'],
                'status' => 'queued',
                'metadata' => [
                    'actor_id' => $event->actor?->id,
                    'event_payload' => $event->payload,
                    'recipient_name' => $subscription->user?->name,
                ],
            ]);

            SendNotificationMail::dispatch($log->id, $email)->onQueue('notifications');
        }
    }
}
