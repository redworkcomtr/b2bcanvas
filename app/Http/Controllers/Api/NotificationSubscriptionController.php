<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSubscriptionController extends Controller
{
    public function update(Request $request, NotificationSubscription $subscription): JsonResponse
    {
        abort_unless($subscription->user()->where('tenant_id', $request->user()->tenant_id)->exists(), 404);
        abort_unless($subscription->user_id === $request->user()->id || $request->user()->hasPermission('manage_users'), 403);

        $validated = $request->validate([
            'email' => ['sometimes', 'email'],
            'is_subscribed' => ['sometimes', 'boolean'],
        ]);

        $subscription->update($validated);

        return response()->json($subscription);
    }
}
