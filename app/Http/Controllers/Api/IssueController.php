<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class IssueController extends Controller
{
    public function store(Request $request, string $type): JsonResponse
    {
        abort_unless(in_array($type, ['ticket', 'claim'], true), 404);
        Gate::authorize('create-issue');

        $validated = $request->validate([
            'order_id' => ['nullable', 'exists:orders,id'],
            'request_type' => ['nullable', 'string', 'max:80'],
            'reasons' => ['required', 'array', 'min:1'],
            'description' => ['required', 'string', 'max:5000'],
            'contact' => ['required', 'array'],
        ]);

        $tenantId = $request->user()->tenant_id;
        if (! empty($validated['order_id'])) {
            $belongsToTenant = Order::query()
                ->forTenant($tenantId)
                ->whereKey($validated['order_id'])
                ->exists();

            if (! $belongsToTenant) {
                throw ValidationException::withMessages(['order_id' => ['The selected order does not belong to this tenant.']]);
            }
        }

        $issue = Issue::query()->create([
            ...$validated,
            'tenant_id' => $tenantId,
            'type' => $type,
            'status' => 'open',
            'total_notes_count' => 1,
            'unread_notes_count' => 0,
            'last_activity_at' => now(),
        ]);

        $issue->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['description'],
            'attachments' => [],
        ]);

        return response()->json($issue->load(['order', 'comments']), 201);
    }
}
