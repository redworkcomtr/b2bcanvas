<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\User;

class IssueWorkflowService
{
    /**
     * @param  array<int, array<string, mixed>>  $attachments
     */
    public function comment(Issue $issue, User $user, string $body, array $attachments = [], bool $internal = false): Issue
    {
        $comment = $issue->comments()->create([
            'user_id' => $user->id,
            'body' => $body,
            'attachments' => $attachments,
            'internal' => $internal,
        ]);

        $unreadIncrement = $issue->assigned_to_id
            && $issue->assigned_to_id !== $user->id
            && ! $internal
            ? 1
            : 0;

        $issue->update([
            'total_notes_count' => $issue->total_notes_count + 1,
            'unread_notes_count' => $issue->unread_notes_count + $unreadIncrement,
            'last_activity_at' => now(),
        ]);

        $this->audit($issue, $user, 'issue.comment_added', [
            'comment_id' => $comment->id,
            'internal' => $internal,
            'attachment_count' => count($attachments),
        ]);

        return $this->freshIssue($issue);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Issue $issue, User $user, array $attributes): Issue
    {
        $before = $issue->only(['status', 'priority', 'assigned_to_id']);
        $updates = [];

        foreach (['status', 'priority', 'assigned_to_id'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $updates[$field] = $attributes[$field];
            }
        }

        if (array_key_exists('status', $updates)) {
            if ($updates['status'] === 'resolved') {
                $updates['resolved_at'] = now();
                $updates['closed_at'] = null;
            } elseif ($updates['status'] === 'closed') {
                $updates['resolved_at'] = $issue->resolved_at ?? now();
                $updates['closed_at'] = now();
            } elseif (in_array($updates['status'], ['open', 'in_progress', 'waiting_customer'], true)) {
                $updates['resolved_at'] = null;
                $updates['closed_at'] = null;
            }
        }

        if ($updates !== []) {
            $updates['last_activity_at'] = now();
            $issue->update($updates);
        }

        $this->audit($issue, $user, 'issue.updated', [
            'before' => $before,
            'after' => $issue->fresh()->only(['status', 'priority', 'assigned_to_id']),
        ]);

        return $this->freshIssue($issue);
    }

    public function markRead(Issue $issue, User $user): Issue
    {
        $issue->update([
            'unread_notes_count' => 0,
            'last_read_at' => now(),
        ]);

        $this->audit($issue, $user, 'issue.marked_read');

        return $this->freshIssue($issue);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(Issue $issue, User $user, string $event, array $metadata = []): void
    {
        AuditLog::query()->create([
            'tenant_id' => $issue->tenant_id,
            'user_id' => $user->id,
            'event' => $event,
            'auditable_type' => Issue::class,
            'auditable_id' => $issue->id,
            'metadata' => [
                'issue_id' => $issue->id,
                'type' => $issue->type,
                'order_id' => $issue->order_id,
                ...$metadata,
            ],
        ]);
    }

    private function freshIssue(Issue $issue): Issue
    {
        return $issue->fresh(['order', 'comments.user', 'assignedTo']);
    }
}
