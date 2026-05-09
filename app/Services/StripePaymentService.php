<?php

namespace App\Services;

use App\Events\NotificationRequested;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class StripePaymentService
{
    private const string StripeApiUrl = 'https://api.stripe.com/v1';

    public function __construct(
        private readonly OrderStatusService $orderStatusService,
    ) {}

    public function createIntent(Order $order, User $actor, bool $forceNew = false): Payment
    {
        $subtotal = (int) ($order->totals['subtotal_cents'] ?? 0);
        $currency = strtoupper((string) ($order->totals['currency'] ?? config('services.stripe.currency', 'USD')));

        if ($subtotal <= 0) {
            $order->update([
                'payment_status' => 'not_required',
                'paid_at' => now(),
            ]);

            return $this->upsertPaymentRecord($order, $actor, [
                'provider' => 'stripe',
                'amount_cents' => 0,
                'currency' => $currency,
                'status' => 'succeeded',
                'provider_payment_intent_id' => 'zero_'.$order->uuid,
            ]);
        }

        if (! $forceNew) {
            $payment = $order->payments()->latest()->first();

            if ($payment && in_array($payment->status, ['requires_payment_method', 'requires_confirmation', 'requires_action', 'processing'], true)) {
                return $payment;
            }
        }

        $response = $this->stripeRequest('post', '/payment_intents', [
            'amount' => $subtotal,
            'currency' => strtolower($currency),
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'order_id' => (string) $order->id,
                'order_uuid' => $order->uuid,
                'tenant_id' => (string) $order->tenant_id,
            ],
        ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'payment' => ['Stripe payment intent could not be created.'],
            ]);
        }

        $payload = $response->json();
        $intentId = (string) ($payload['id'] ?? '');
        $clientSecret = (string) ($payload['client_secret'] ?? '');

        if ($intentId === '' || $clientSecret === '') {
            throw ValidationException::withMessages([
                'payment' => ['Stripe response was missing required payment intent fields.'],
            ]);
        }

        return $this->upsertPaymentRecord($order, $actor, [
            'provider' => 'stripe',
            'amount_cents' => $subtotal,
            'currency' => $currency,
            'status' => (string) ($payload['status'] ?? 'created'),
            'provider_payment_intent_id' => $intentId,
            'metadata' => [
                ...(array) $payload,
                'client_secret' => $clientSecret,
            ],
        ]);
    }

    /**
     * @return array<string, string|int|null>
     */
    public function confirmIntent(Order $order, string $intentId): array
    {
        $payment = $order->payments()
            ->where('provider_payment_intent_id', $intentId)
            ->first();

        if (! $payment) {
            throw ValidationException::withMessages([
                'payment' => ['Payment intent was not found for this order.'],
            ]);
        }

        $response = $this->stripeRequest('get', '/payment_intents/'.rawurlencode($intentId));
        $payload = $response->json();
        $status = (string) ($payload['status'] ?? 'failed');

        $payment->update([
            'status' => $status,
            'metadata' => [
                ...(array) $payment->metadata,
                'provider_response' => $payload,
            ],
        ]);

        if ($status === 'succeeded') {
            $order->refresh();
            if ($order->status === 'draft') {
                $order->update(['status' => 'verified']);
            }

            $this->markOrderPaid($order, (int) ($order->totals['subtotal_cents'] ?? 0));
        } elseif (in_array($status, ['failed', 'canceled'], true)) {
            $order->update(['payment_status' => 'failed']);
            $payment->update(['last_error' => (string) ($payload['last_payment_error']['message'] ?? 'Payment did not complete.')]);
        } elseif (in_array($status, ['requires_payment_method', 'requires_action', 'processing', 'requires_confirmation'], true)) {
            $order->update(['payment_status' => 'unpaid']);
        }

        $order->refresh();

        return [
            'payment_status' => $order->payment_status,
            'order_status' => $order->status,
            'payment_intent_status' => $status,
            'requires_action' => in_array($status, ['requires_action', 'requires_confirmation'], true),
        ];
    }

    public function handleWebhookPayload(string $rawPayload, ?string $signatureHeader): void
    {
        $this->verifyWebhookSignature($rawPayload, $signatureHeader);
        $event = json_decode($rawPayload, true);

        if (! is_array($event)) {
            throw ValidationException::withMessages([
                'payload' => ['Invalid webhook payload.'],
            ]);
        }

        $eventType = (string) ($event['type'] ?? '');
        if (! str_starts_with($eventType, 'payment_intent.')) {
            return;
        }

        $paymentIntent = (array) ($event['data']['object'] ?? []);
        $intentId = (string) ($paymentIntent['id'] ?? '');

        if ($intentId === '') {
            throw ValidationException::withMessages([
                'payload' => ['Webhook payload missing payment intent id.'],
            ]);
        }

        $payment = Payment::query()->where('provider_payment_intent_id', $intentId)->first();
        if (! $payment) {
            return;
        }

        $status = (string) ($paymentIntent['status'] ?? 'failed');
        $payment->update([
            'status' => $status,
            'metadata' => [
                ...(array) $payment->metadata,
                'webhook_event' => $eventType,
                'webhook_received_at' => now()->toAtomString(),
            ],
        ]);

        $order = $payment->order;
        if (! $order) {
            return;
        }

        if ($eventType === 'payment_intent.succeeded') {
            $order->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);
            $this->markOrderPaid($order, (int) ($order->totals['subtotal_cents'] ?? 0));
        } elseif ($eventType === 'payment_intent.payment_failed') {
            $order->update(['payment_status' => 'failed']);
            $payment->update(['last_error' => (string) ($paymentIntent['last_payment_error']['message'] ?? 'Payment failed')]);
        } elseif ($eventType === 'payment_intent.canceled') {
            $order->update(['payment_status' => 'failed']);
        }
    }

    public function verifyWebhookSignature(string $payload, ?string $header): void
    {
        $secret = config('services.stripe.webhook_secret');
        if (! $secret) {
            return;
        }

        if (! $header) {
            throw ValidationException::withMessages([
                'payment' => ['Webhook signature header is missing.'],
            ]);
        }

        $items = [];
        foreach (explode(',', $header) as $piece) {
            if (! str_contains($piece, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $piece, 2);
            $items[$key] = $value;
        }

        if (! isset($items['t'], $items['v1'])) {
            throw ValidationException::withMessages([
                'payment' => ['Webhook signature format is invalid.'],
            ]);
        }

        $signedPayload = $items['t'].'.'.$payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);
        if (! hash_equals($expectedSignature, $items['v1'])) {
            throw ValidationException::withMessages([
                'payment' => ['Webhook signature is invalid.'],
            ]);
        }

        $eventTimestamp = Carbon::createFromTimestamp((int) $items['t'])->getTimestamp();
        if (abs(now()->getTimestamp() - $eventTimestamp) > 300) {
            throw ValidationException::withMessages([
                'payment' => ['Webhook timestamp expired.'],
            ]);
        }
    }

    private function stripeRequest(string $method, string $path, array $payload = []): Response
    {
        $secret = config('services.stripe.secret_key');
        if (! $secret) {
            throw ValidationException::withMessages([
                'payment' => ['Stripe secret key is not configured.'],
            ]);
        }

        $request = Http::asForm()->withToken($secret);
        $url = self::StripeApiUrl.$path;

        return match ($method) {
            'post' => $request->post($url, $payload),
            default => $request->get($url),
        };
    }

    private function upsertPaymentRecord(Order $order, User $actor, array $attributes): Payment
    {
        $providerIntentId = (string) $attributes['provider_payment_intent_id'];

        $payment = $order->payments()->updateOrCreate(
            ['provider_payment_intent_id' => $providerIntentId],
            [
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'user_id' => $actor->id,
                'provider' => $attributes['provider'],
                'amount_cents' => (int) $attributes['amount_cents'],
                'currency' => (string) $attributes['currency'],
                'status' => (string) $attributes['status'],
                'metadata' => (array) ($attributes['metadata'] ?? []),
                'last_error' => (string) ($attributes['last_error'] ?? null),
            ],
        );

        if ($order->payment_status === 'not_required') {
            return $payment;
        }

        if ($payment->status === 'succeeded') {
            $this->markOrderPaid($order, (int) ($order->totals['subtotal_cents'] ?? 0));

            return $payment;
        }

        $order->update(['payment_status' => 'unpaid']);

        return $payment;
    }

    private function markOrderPaid(Order $order, int $amountCents): void
    {
        $freshOrder = $order->fresh();

        if (! $freshOrder) {
            return;
        }

        $wasAlreadyPaid = in_array($freshOrder->payment_status, ['paid', 'not_required'], true);

        if (! $wasAlreadyPaid) {
            $freshOrder->update(['payment_status' => 'paid', 'paid_at' => now()]);
            $freshOrder->refresh();
        }

        if ($freshOrder->status === 'verified') {
            $this->orderStatusService->transition($freshOrder, 'submitted', null, 'Payment completed; order released to production queue.', [
                'amount_cents' => $amountCents,
            ]);
        }

        if ($wasAlreadyPaid) {
            return;
        }

        NotificationRequested::dispatch(
            'ORDER_PAYMENT_COMPLETED',
            $freshOrder->tenant_id,
            [
                'order_id' => $freshOrder->id,
                'order_number' => $freshOrder->order_number,
                'amount_cents' => $amountCents,
                'currency' => $freshOrder->totals['currency'] ?? 'USD',
            ],
            null,
        );
    }
}
