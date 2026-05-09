<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function intent(Request $request, string $uuid, StripePaymentService $paymentService): JsonResponse
    {
        $order = $this->tenantOrder($request, $uuid);
        Gate::authorize('update', $order);

        $validated = $request->validate([
            'force_new_intent' => ['nullable', 'boolean'],
        ]);

        $payment = $paymentService->createIntent($order, $request->user(), (bool) ($validated['force_new_intent'] ?? false));
        $order->load('payments');

        return response()->json([
            'order' => $order,
            'payment' => [
                'provider_payment_intent_id' => $payment->provider_payment_intent_id,
                'status' => $payment->status,
                'amount_cents' => $payment->amount_cents,
                'currency' => $payment->currency,
                'metadata' => $payment->metadata,
            ],
            'client_secret' => $payment->metadata['client_secret'] ?? null,
        ], 201);
    }

    public function confirm(Request $request, string $uuid, StripePaymentService $paymentService): JsonResponse
    {
        $order = $this->tenantOrder($request, $uuid);
        Gate::authorize('update', $order);

        $validated = $request->validate([
            'payment_intent_id' => ['required', 'string'],
        ]);

        $result = $paymentService->confirmIntent($order, $validated['payment_intent_id']);

        $order->refresh();

        return response()->json([
            'order' => $order->fresh(['items.variant.productType', 'payments']),
            'result' => $result,
        ]);
    }

    public function webhook(Request $request, StripePaymentService $paymentService): JsonResponse
    {
        $paymentService->handleWebhookPayload($request->getContent(), $request->header('stripe-signature'));

        return response()->json(['status' => 'ok']);
    }

    private function tenantOrder(Request $request, string $uuid): Order
    {
        $order = Order::query()
            ->forTenant($request->user()->tenant_id)
            ->where('uuid', $uuid)
            ->first();

        if (! $order) {
            abort(404);
        }

        return $order;
    }
}
