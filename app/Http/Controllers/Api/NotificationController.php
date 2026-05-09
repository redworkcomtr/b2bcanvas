<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationMail;
use App\Models\NotificationMailLog;
use App\Models\NotificationSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function unsubscribe(string $token): JsonResponse
    {
        $subscription = NotificationSubscription::query()
            ->with('user')
            ->where('unsubscribe_token', $token)
            ->firstOrFail();

        if (! $subscription->is_subscribed) {
            return response()->json([
                'message' => 'This address is already unsubscribed.',
            ]);
        }

        $subscription->update(['is_subscribed' => false]);

        return response()->json([
            'message' => 'Notification preferences updated. You have unsubscribed for this event.',
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('manage_users'), 403);

        $limit = min(120, max(20, (int) $request->query('limit', 80)));

        return response()->json(
            NotificationMailLog::query()
                ->forTenant($request->user()->tenant_id)
                ->latest()
                ->limit($limit)
                ->get(),
        );
    }

    public function preview(Request $request, NotificationMailLog $log): JsonResponse
    {
        abort_unless($request->user()->hasPermission('manage_users'), 403);
        abort_unless($request->user()->tenant_id === $log->tenant_id, 404);

        return response()->json([
            'subject' => $log->subject,
            'body_html' => $log->body_html,
            'body_text' => $log->body_text,
            'status' => $log->status,
            'attempts' => $log->attempts,
            'error_message' => $log->error_message,
            'max_attempts' => $log->max_attempts,
        ]);
    }

    public function retry(Request $request, NotificationMailLog $log): JsonResponse
    {
        abort_unless($request->user()->hasPermission('manage_users'), 403);
        abort_unless($request->user()->tenant_id === $log->tenant_id, 404);

        $validated = $request->validate([
            'recipient_email' => ['sometimes', 'required', 'email'],
        ]);

        $recipient = $validated['recipient_email'] ?? $log->recipient_email;

        if ($recipient !== $log->recipient_email) {
            $log->update([
                'recipient_email' => $recipient,
                'status' => 'queued',
                'error_message' => null,
                'attempts' => 0,
            ]);
        } else {
            $log->update([
                'status' => 'queued',
                'error_message' => null,
            ]);
        }

        SendNotificationMail::dispatch($log->id, $recipient)->onQueue('notifications');

        return response()->json($log->fresh());
    }
}
