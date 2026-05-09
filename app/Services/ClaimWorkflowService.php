<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ClaimWorkflowService
{
    public function resolve(Issue $issue, User $user, array $payload): Issue
    {
        if ($issue->type !== 'claim') {
            throw ValidationException::withMessages([
                'issue' => ['The selected issue is not a claim.'],
            ]);
        }

        $decision = (string) $payload['decision'];
        $amount = array_key_exists('amount_cents', $payload) ? (int) $payload['amount_cents'] : null;
        $evidenceFiles = $payload['evidence_files'] ?? [];
        $notes = trim((string) ($payload['notes'] ?? ''));
        $financeReference = array_key_exists('finance_reference', $payload) ? trim((string) $payload['finance_reference']) : null;
        $productionOutcome = array_key_exists('production_outcome', $payload) ? trim((string) $payload['production_outcome']) : null;
        $currency = array_key_exists('currency', $payload) ? trim((string) $payload['currency']) : null;

        $decisionPayload = [
            'amount_cents' => $amount,
            'currency' => $currency ?: 'USD',
            'finance_reference' => $financeReference === '' ? null : $financeReference,
            'production_outcome' => $productionOutcome === '' ? null : $productionOutcome,
            'notes' => $notes === '' ? null : $notes,
            'evidence_files' => $evidenceFiles,
        ];

        $resolution = $issue->claimResolutions()->create([
            'user_id' => $user->id,
            'decision' => $decision,
            ...$decisionPayload,
        ]);

        $issue->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'closed_at' => null,
            'last_activity_at' => now(),
        ]);

        if ($notes !== '') {
            $this->commentDecision($issue, $user, [
                'decision' => $decision,
                'amount_cents' => $amount,
                'currency' => $currency ?: 'USD',
                'finance_reference' => $financeReference,
                'production_outcome' => $productionOutcome,
                'notes' => $notes,
            ], $evidenceFiles);
        }

        $this->audit($issue, $user, 'claim.decided', [
            'decision' => $decision,
            'resolution_id' => $resolution->id,
            'amount_cents' => $amount,
            'has_evidence' => ! empty($evidenceFiles),
        ]);

        return $this->freshIssue($issue);
    }

    /**
     * @param  array<string, string|int|array>  $payload
     * @param  array<int, array<string, mixed>>  $evidenceFiles
     */
    private function commentDecision(Issue $issue, User $user, array $payload, array $evidenceFiles): void
    {
        $summary = "Claim decision: {$payload['decision']}.";
        if (! empty($payload['amount_cents'])) {
            $summary .= " Amount: {$payload['amount_cents']} {$payload['currency']}.";
        }

        if (! empty($payload['finance_reference'])) {
            $summary .= " Reference: {$payload['finance_reference']}.";
        }

        if (! empty($payload['production_outcome'])) {
            $summary .= " Production: {$payload['production_outcome']}.";
        }

        $summary .= " {$payload['notes']}";

        $issue->comments()->create([
            'user_id' => $user->id,
            'body' => $summary,
            'attachments' => $evidenceFiles,
            'internal' => false,
        ]);

        $unreadIncrement = $issue->assigned_to_id && $issue->assigned_to_id !== $user->id ? 1 : 0;

        $issue->update([
            'total_notes_count' => $issue->total_notes_count + 1,
            'unread_notes_count' => $issue->unread_notes_count + $unreadIncrement,
        ]);

        $this->audit($issue, $user, 'claim.decision_commented', [
            'comment_body' => $summary,
            'attachment_count' => count($evidenceFiles),
        ]);
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
        return $issue->fresh(['order', 'comments.user', 'assignedTo', 'claimResolution.user']);
    }
}
