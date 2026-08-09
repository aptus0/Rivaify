<?php

namespace App\Core\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrivateAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('internal_admin.enforce_private_network')) {
            return $next($request);
        }

        if (strtolower($request->getHost()) !== strtolower((string) config('internal_admin.host'))) {
            abort(404);
        }

        if (! $this->ipAllowed($request->ip())) {
            abort(404);
        }

        return $next($request);
    }

    private function ipAllowed(?string $ip): bool
    {
        if ($ip === null || $ip === '') {
            return false;
        }

        foreach ((array) config('internal_admin.allowed_cidrs') as $cidr) {
            if ($this->matchesCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function matchesCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return hash_equals($cidr, $ip);
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $ipBinary = inet_pton($ip);
        $subnetBinary = inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($subnetBinary, 0, $bytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = ~((1 << (8 - $remainder)) - 1) & 0xff;

        return (ord($ipBinary[$bytes]) & $mask) === (ord($subnetBinary[$bytes]) & $mask);
    }
}
