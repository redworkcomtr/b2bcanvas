<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\MediaFile;
use App\Services\ClaimWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClaimController extends Controller
{
    public function resolve(Request $request, Issue $issue, ClaimWorkflowService $workflow): JsonResponse
    {
        $this->authorizeClaim($request, $issue);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['credit', 'refund', 'reprint', 'reject'])],
            'amount_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'finance_reference' => ['nullable', 'string', 'max:120'],
            'production_outcome' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'evidence_files' => ['nullable', 'array'],
            'evidence_files.*.id' => ['required_with:evidence_files', 'integer'],
        ]);

        if (in_array($validated['decision'], ['credit', 'refund'], true) && empty($validated['amount_cents'])) {
            throw ValidationException::withMessages([
                'amount_cents' => ['Amount is required for credit and refund decisions.'],
            ]);
        }

        $evidenceFiles = $validated['evidence_files'] ?? [];
        $this->assertMediaBelongsToTenant($evidenceFiles, $request->user()->tenant_id);

        $updated = $workflow->resolve($issue, $request->user(), [
            'decision' => $validated['decision'],
            'amount_cents' => $validated['amount_cents'] ?? null,
            'currency' => $validated['currency'] ?? 'USD',
            'finance_reference' => $validated['finance_reference'] ?? null,
            'production_outcome' => $validated['production_outcome'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'evidence_files' => $evidenceFiles,
        ]);

        $updated->load('claimResolution');

        return response()->json($updated);
    }

    private function authorizeClaim(Request $request, Issue $issue): void
    {
        abort_unless($request->user()->hasPermission('manage_issues'), 403);
        abort_unless($issue->tenant_id === $request->user()->tenant_id, 404);
        abort_unless($issue->type === 'claim', 404);
    }

    private function assertMediaBelongsToTenant(array $evidenceFiles, int $tenantId): void
    {
        $mediaIds = collect($evidenceFiles)
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
                'evidence_files' => ['One or more evidence files do not belong to this tenant.'],
            ]);
        }
    }
}
