<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\MediaFile;
use App\Models\Order;
use App\Models\User;
use App\Services\IssueWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class IssueController extends Controller
{
    public function show(Request $request, Issue $issue): JsonResponse
    {
        $this->authorizeIssue($request, $issue);

        return response()->json($this->freshIssue($issue));
    }

    public function store(Request $request, string $type): JsonResponse
    {
        abort_unless(in_array($type, ['ticket', 'claim'], true), 404);
        Gate::authorize('create-issue');

        $validated = $request->validate([
            'order_id' => ['nullable', 'exists:orders,id'],
            'assigned_to_id' => ['nullable', 'exists:users,id'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
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

        if (! empty($validated['assigned_to_id'])) {
            $this->assertAssignableUser($validated['assigned_to_id'], $tenantId);
        }

        $issue = Issue::query()->create([
            ...$validated,
            'tenant_id' => $tenantId,
            'type' => $type,
            'status' => 'open',
            'priority' => $validated['priority'] ?? 'normal',
            'total_notes_count' => 1,
            'unread_notes_count' => 0,
            'last_activity_at' => now(),
            'last_read_at' => now(),
        ]);

        $issue->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['description'],
            'attachments' => [],
            'internal' => false,
        ]);

        return response()->json($this->freshIssue($issue), 201);
    }

    public function update(Request $request, Issue $issue, IssueWorkflowService $workflow): JsonResponse
    {
        $this->authorizeIssue($request, $issue);

        $validated = $request->validate([
            'status' => ['nullable', 'in:open,in_progress,waiting_customer,resolved,closed'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'assigned_to_id' => ['nullable', 'exists:users,id'],
        ]);

        if (array_key_exists('assigned_to_id', $validated) && $validated['assigned_to_id']) {
            $this->assertAssignableUser($validated['assigned_to_id'], $request->user()->tenant_id);
        }

        return response()->json($workflow->update($issue, $request->user(), $validated));
    }

    public function comment(Request $request, Issue $issue, IssueWorkflowService $workflow): JsonResponse
    {
        $this->authorizeIssue($request, $issue);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*.id' => ['nullable', 'integer'],
            'internal' => ['nullable', 'boolean'],
        ]);

        $attachments = $validated['attachments'] ?? [];
        $this->assertMediaBelongsToTenant($attachments, $request->user()->tenant_id);

        return response()->json($workflow->comment(
            $issue,
            $request->user(),
            $validated['body'],
            $attachments,
            (bool) ($validated['internal'] ?? false),
        ));
    }

    public function markRead(Request $request, Issue $issue, IssueWorkflowService $workflow): JsonResponse
    {
        $this->authorizeIssue($request, $issue);

        return response()->json($workflow->markRead($issue, $request->user()));
    }

    private function authorizeIssue(Request $request, Issue $issue): void
    {
        abort_unless($request->user()->hasPermission('manage_issues'), 403);
        abort_unless($issue->tenant_id === $request->user()->tenant_id, 404);
    }

    private function assertAssignableUser(int $userId, int $tenantId): void
    {
        $user = User::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($userId)
            ->first();

        if (! $user || ! $user->hasPermission('manage_issues')) {
            throw ValidationException::withMessages([
                'assigned_to_id' => ['The selected assignee must be a support-enabled user in this tenant.'],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     */
    private function assertMediaBelongsToTenant(array $attachments, int $tenantId): void
    {
        $mediaIds = collect($attachments)
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();

        if ($mediaIds->isEmpty()) {
            return;
        }

        $validCount = MediaFile::query()
            ->forTenant($tenantId)
            ->whereIn('id', $mediaIds)
            ->count();

        if ($validCount !== $mediaIds->count()) {
            throw ValidationException::withMessages([
                'attachments' => ['One or more attachments do not belong to this tenant.'],
            ]);
        }
    }

    private function freshIssue(Issue $issue): Issue
    {
        return $issue->fresh(['order', 'comments.user', 'assignedTo', 'claimResolution', 'claimResolution.user']);
    }
}
