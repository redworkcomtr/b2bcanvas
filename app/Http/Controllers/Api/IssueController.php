<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IssueController extends Controller
{
    public function store(Request $request, string $type): JsonResponse
    {
        abort_unless(in_array($type, ['ticket', 'claim'], true), 404);

        $validated = $request->validate([
            'order_id' => ['nullable', 'exists:orders,id'],
            'request_type' => ['nullable', 'string', 'max:80'],
            'reasons' => ['required', 'array', 'min:1'],
            'description' => ['required', 'string', 'max:5000'],
            'contact' => ['required', 'array'],
        ]);

        $issue = Issue::query()->create([
            ...$validated,
            'tenant_id' => Tenant::query()->firstOrFail()->id,
            'type' => $type,
            'status' => 'open',
            'total_notes_count' => 1,
            'unread_notes_count' => 0,
            'last_activity_at' => now(),
        ]);

        $issue->comments()->create([
            'body' => $validated['description'],
            'attachments' => [],
        ]);

        return response()->json($issue->load(['order', 'comments']), 201);
    }
}
