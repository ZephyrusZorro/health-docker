<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Events\UserLoggedIn;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\UserResource;
use App\Models\Identity\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Authentication — all flows that don't touch RBAC model data.
 *
 * Endpoints:
 *   POST /api/v1/auth/login          issue a token (brute-force throttled)
 *   POST /api/v1/auth/logout         revoke current token
 *   GET  /api/v1/auth/me             current user + roles + permissions
 *   POST /api/v1/auth/change-password
 */
class AuthController extends ApiController
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $key = 'login:' . strtolower($data['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return $this->fail("Too many login attempts. Try again in {$seconds}s.", 429);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        if (! $user->is_active) {
            return $this->fail('Account is deactivated. Contact your administrator.', 403);
        }

        RateLimiter::clear($key);

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        event(new UserLoggedIn($user, $request->ip(), $request->userAgent()));

        $token = $user->createToken(
            name: $request->userAgent() ?: 'api-session',
            expiresAt: now()->addMinutes(config('sanctum.expiration', 480)),
        )->plainTextToken;

        return $this->ok([
            'token' => $token,
            'user'  => new UserResource($user->load(['roles', 'permissions', 'facility', 'provider'])),
        ], 'Logged in');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return $this->ok(null, 'Logged out');
    }

    public function me(Request $request)
    {
        return $this->ok(new UserResource(
            $request->user()->load(['roles', 'permissions', 'facility', 'provider', 'staff'])
        ));
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->update(['password' => $data['new_password']]);  // hashed cast
        $user->tokens()->delete(); // invalidate every session

        return $this->ok(null, 'Password updated. Please log in again.');
    }
}
