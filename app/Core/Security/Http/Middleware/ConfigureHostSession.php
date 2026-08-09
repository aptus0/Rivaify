<?php

namespace App\Core\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigureHostSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('session_hardening.enabled')) {
            return $next($request);
        }

        $host = strtolower($request->getHost());
        $settings = config('session_hardening.hosts')[$host] ?? [];
        if ($settings === []) {
            return $next($request);
        }

        if ((bool) config('session_hardening.host_only')) {
            config(['session.domain' => null]);
        }

        config([
            'session.secure' => true,
            'session.http_only' => true,
            'session.path' => '/',
        ]);

        if (isset($settings['cookie'])) {
            config(['session.cookie' => $settings['cookie']]);
        }

        if (isset($settings['same_site'])) {
            config(['session.same_site' => $settings['same_site']]);
        }

        if (isset($settings['lifetime'])) {
            config(['session.lifetime' => (int) $settings['lifetime']]);
        }

        return $next($request);
    }
}
