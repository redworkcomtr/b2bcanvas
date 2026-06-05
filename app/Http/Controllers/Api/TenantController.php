<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class TenantController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('manage_tenant'), 403);

        /** @var User $actor */
        $actor = $request->user();
        /** @var Tenant $tenant */
        $tenant = $actor->tenant()->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'support_email' => ['sometimes', 'nullable', 'email', 'max:180'],
            'settings' => ['sometimes', 'array'],
            'settings.currency' => ['nullable', 'string', 'size:3'],
            'settings.timezone' => ['nullable', 'string', 'max:80'],
            'settings.default_shipping_service' => ['nullable', 'string', 'max:120'],
            'settings.order_prefix' => ['nullable', 'string', 'max:24'],
        ]);

        $settings = array_filter([
            ...($tenant->settings ?? []),
            ...Arr::get($validated, 'settings', []),
        ], fn ($value): bool => $value !== null && $value !== '');

        $changes = [];

        if (array_key_exists('name', $validated)) {
            $changes['name'] = $validated['name'];
        }

        if (array_key_exists('support_email', $validated)) {
            $changes['support_email'] = $validated['support_email'];
        }

        if (array_key_exists('settings', $validated)) {
            $changes['settings'] = $settings;
        }

        $tenant->update($changes);

        AuditLog::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $actor->id,
            'event' => 'tenant.updated',
            'auditable_type' => Tenant::class,
            'auditable_id' => $tenant->id,
            'metadata' => $changes,
        ]);

        return response()->json($tenant->fresh());
    }
}
