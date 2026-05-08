<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function session(Request $request): JsonResponse
    {
        $user = $request->user()?->load('tenant');

        if (! $user || ! $user->active || ! $user->tenant) {
            Auth::logout();

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json($this->payload($user));
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $user = User::query()
            ->with('tenant')
            ->where('email', $validated['email'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password) || ! $user->active || ! $user->tenant) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match an active workspace user.'],
            ]);
        }

        Auth::login($user, (bool) ($validated['remember'] ?? false));
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        AuditLog::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'event' => 'auth.login',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'metadata' => ['ip' => $request->ip()],
        ]);

        return response()->json($this->payload($user->fresh('tenant')));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            AuditLog::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'event' => 'auth.logout',
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'metadata' => ['ip' => $request->ip()],
            ]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($validated);

        return response()->json([
            'message' => $status === Password::RESET_LINK_SENT
                ? 'Password reset instructions have been sent.'
                : 'If that e-mail belongs to an account, reset instructions will be sent.',
        ], 202);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:10'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'active' => true,
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => 'Password has been reset.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        return [
            'user' => $user,
            'tenant' => $user->tenant,
            'abilities' => $user->permissions(),
        ];
    }
}
