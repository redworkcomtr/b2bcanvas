<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\NotificationSubscription;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $tenantId = $request->user()->tenant_id;

        return response()->json([
            'users' => User::query()
                ->where('tenant_id', $tenantId)
                ->orderByRaw("case role when 'owner' then 1 when 'admin' then 2 when 'operations' then 3 when 'support' then 4 else 5 end")
                ->orderBy('name')
                ->get(),
            'invites' => UserInvite::query()
                ->forTenant($tenantId)
                ->latest()
                ->get(),
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        Gate::authorize('invite', User::class);

        $actor = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in($actor->assignableRoles())],
        ]);

        $user = User::query()->create([
            'tenant_id' => $actor->tenant_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'active' => false,
            'invited_at' => now(),
            'password' => Str::random(48),
        ]);

        $invite = UserInvite::query()->updateOrCreate(
            ['tenant_id' => $actor->tenant_id, 'email' => $validated['email']],
            [
                'invited_by_id' => $actor->id,
                'role' => $validated['role'],
                'token' => Str::random(64),
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
            ],
        );

        foreach (['ORDER_SHIPPED', 'ORDER_ACTION_NEEDED', 'ORDER_ISSUE_COMMENT_ADDED', 'ORDER_VALIDATION_FAILED'] as $event) {
            NotificationSubscription::query()->firstOrCreate([
                'user_id' => $user->id,
                'event' => $event,
            ], [
                'email' => $user->email,
                'is_subscribed' => true,
            ]);
        }

        $this->audit($actor, 'user.invited', $user, ['role' => $user->role]);

        return response()->json([
            'user' => $user,
            'invite' => $invite,
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $actor = $request->user();
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', Rule::in($actor->assignableRoles())],
            'active' => ['sometimes', 'boolean'],
        ]);

        if (($validated['active'] ?? true) === false && $actor->id === $user->id) {
            throw ValidationException::withMessages(['active' => ['You cannot deactivate your own account.']]);
        }

        if (($validated['role'] ?? $user->role) !== 'owner' && $user->isOwner()) {
            $hasOtherOwner = User::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('role', 'owner')
                ->where('active', true)
                ->whereKeyNot($user->id)
                ->exists();

            if (! $hasOtherOwner) {
                throw ValidationException::withMessages(['role' => ['At least one active owner is required.']]);
            }
        }

        $user->update($validated);

        $this->audit($actor, 'user.updated', $user, $validated);

        return response()->json($user->fresh());
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function audit(User $actor, string $event, User $target, array $metadata): void
    {
        AuditLog::query()->create([
            'tenant_id' => $actor->tenant_id,
            'user_id' => $actor->id,
            'event' => $event,
            'auditable_type' => User::class,
            'auditable_id' => $target->id,
            'metadata' => $metadata,
        ]);
    }
}
