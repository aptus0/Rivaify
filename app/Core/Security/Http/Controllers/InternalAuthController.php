<?php

namespace App\Core\Security\Http\Controllers;

use App\Core\Shared\Services\ActivityLogger;
use App\Core\Internal\Support\InternalStaff;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Dedicated login/logout for ins.rivaify.com. Fortify's own /login is
 * domain-locked to app.rivaify.com (config/fortify.php), and — since the
 * host-aware session hardening (config/session_hardening.php) gives
 * ins.rivaify.com its own host-only session cookie, deliberately separate
 * from app.rivaify.com's — a merchant-side login session can never carry
 * over here via a shared cookie. Staff authenticate against the same
 * `users` table (is_rivaify_admin flag, no separate staff table yet), but
 * through their own real login here rather than a cross-subdomain bounce.
 */
class InternalAuthController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function login(Request $request, InternalStaff $internalStaff): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        // Same generic error whether the account doesn't exist, the
        // password is wrong, or the account simply isn't staff — never
        // reveal which case it was.
        if (! $user || ! Auth::validate($credentials) || ! $user->is_rivaify_admin) {
            $this->activity->log('internal.staff_login_failed', [
                'email_hash' => hash('sha256', strtolower((string) $credentials['email'])),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            throw ValidationException::withMessages([
                'email' => 'Bu bilgilerle giriş yapılamadı.',
            ]);
        }

        $staff = $internalStaff->fromUser($user);

        if ($staff->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => 'Bu bilgilerle giriş yapılamadı.',
            ]);
        }

        if (config('internal_admin.require_two_factor') && $staff->two_factor_confirmed_at === null) {
            $this->activity->log('internal.staff_login_blocked_2fa_required', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ], userId: $user->id);

            throw ValidationException::withMessages([
                'email' => 'Internal erişim için iki adımlı doğrulama zorunlu.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('internal_staff_authenticated_at', now()->toIso8601String());
        $request->session()->put('internal_staff_ulid', $staff->ulid);

        $user->forceFill(['last_login_at' => now()])->save();
        $staff->forceFill(['last_login_at' => now()])->save();

        $this->activity->log('internal.staff_login_succeeded', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], userId: $user->id);

        return response()->json(['data' => ['id' => $user->ulid]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $userId = Auth::id();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($userId !== null) {
            $this->activity->log('internal.staff_logout', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ], userId: $userId);
        }

        return response()->json(['data' => true]);
    }
}
