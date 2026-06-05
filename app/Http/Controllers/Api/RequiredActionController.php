<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\RequiredAction;
use App\Services\RequiredActionWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RequiredActionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeWorkflow($request);

        return response()->json(RequiredAction::query()
            ->forTenant($request->user()->tenant_id)
            ->with(['order.items.variant.productType', 'comments.user', 'assignedTo'])
            ->latest('last_activity_at')
            ->get());
    }

    public function show(Request $request, RequiredAction $requiredAction): JsonResponse
    {
        $this->authorizeWorkflow($request, $requiredAction);

        return response()->json($requiredAction->load(['order.items.variant.productType', 'comments.user', 'assignedTo']));
    }

    public function comment(Request $request, RequiredAction $requiredAction, RequiredActionWorkflowService $workflow): JsonResponse
    {
        $this->authorizeWorkflow($request, $requiredAction);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'internal' => ['nullable', 'boolean'],
        ]);

        $workflow->comment($requiredAction, $request->user(), $validated['body'], (bool) ($validated['internal'] ?? false));

        return response()->json($requiredAction->fresh(['order.items.variant.productType', 'comments.user', 'assignedTo']));
    }

    public function resolve(Request $request, RequiredAction $requiredAction, RequiredActionWorkflowService $workflow): JsonResponse
    {
        $this->authorizeWorkflow($request, $requiredAction);

        $validated = $request->validate([
            'resolution' => ['nullable', 'array'],
            'resolution.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'resolution.customer_name' => ['nullable', 'string', 'max:180'],
            'resolution.shipping_address' => ['nullable', 'array'],
            'resolution.shipping_address.line1' => ['nullable', 'string', 'max:180'],
            'resolution.shipping_address.line2' => ['nullable', 'string', 'max:180'],
            'resolution.shipping_address.city' => ['nullable', 'string', 'max:120'],
            'resolution.shipping_address.state' => ['nullable', 'string', 'max:120'],
            'resolution.shipping_address.postal_code' => ['nullable', 'string', 'max:40'],
            'resolution.shipping_address.country' => ['nullable', 'string', 'max:80'],
            'resolution.artwork_media_file_id' => ['nullable', 'integer', 'exists:media_files,id'],
            'resolution.replacement_media_file_id' => ['nullable', 'integer', 'exists:media_files,id'],
            'resolution.decision' => ['nullable', 'string', 'in:skip,process_with_new_number,cancel_existing,acknowledge'],
            'resolution.replacement_order_number' => ['nullable', 'string', 'max:80'],
            'resolution.note' => ['nullable', 'string', 'max:5000'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);

        $resolution = $validated['resolution'] ?? [];
        $variantId = $resolution['product_variant_id'] ?? null;
        if ($variantId) {
            $belongsToTenant = ProductVariant::query()
                ->whereHas('productType', fn ($query) => $query->forTenant($request->user()->tenant_id))
                ->whereKey($variantId)
                ->exists();

            if (! $belongsToTenant) {
                throw ValidationException::withMessages([
                    'resolution.product_variant_id' => ['The selected variant does not belong to this tenant.'],
                ]);
            }
        }

        $mediaId = $resolution['artwork_media_file_id'] ?? $resolution['replacement_media_file_id'] ?? null;
        if ($mediaId) {
            $belongsToTenant = MediaFile::query()
                ->forTenant($request->user()->tenant_id)
                ->whereKey($mediaId)
                ->exists();

            if (! $belongsToTenant) {
                throw ValidationException::withMessages([
                    'resolution.artwork_media_file_id' => ['The selected artwork file does not belong to this tenant.'],
                ]);
            }
        }

        if (
            $requiredAction->type === 'duplicate_order'
            && ($resolution['decision'] ?? null) === 'process_with_new_number'
            && isset($resolution['replacement_order_number'])
            && Order::query()
                ->forTenant($request->user()->tenant_id)
                ->where('order_number', $resolution['replacement_order_number'])
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'resolution.replacement_order_number' => ['The replacement order number is already in use.'],
            ]);
        }

        return response()->json($workflow->resolve(
            $requiredAction,
            $request->user(),
            $resolution,
            $validated['comment'] ?? null,
        ));
    }

    public function reopen(Request $request, RequiredAction $requiredAction, RequiredActionWorkflowService $workflow): JsonResponse
    {
        $this->authorizeWorkflow($request, $requiredAction);

        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);

        return response()->json($workflow->reopen($requiredAction, $request->user(), $validated['comment'] ?? null));
    }

    public function escalate(Request $request, RequiredAction $requiredAction, RequiredActionWorkflowService $workflow): JsonResponse
    {
        $this->authorizeWorkflow($request, $requiredAction);

        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', 'in:normal,high,urgent'],
        ]);

        return response()->json($workflow->escalate(
            $requiredAction,
            $request->user(),
            $validated['comment'] ?? null,
            $validated['priority'] ?? 'urgent',
        ));
    }

    private function authorizeWorkflow(Request $request, ?RequiredAction $requiredAction = null): void
    {
        abort_unless($request->user()->hasPermission('manage_mappings'), 403);

        if ($requiredAction) {
            abort_unless($requiredAction->tenant_id === $request->user()->tenant_id, 404);
        }
    }
}
