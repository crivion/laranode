<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SimulateDemoActions
{
    /**
     * Prevent a public demo from mutating Laravel data or the host system.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('laranode.demo.enabled')) {
            return $next($request);
        }

        if ($request->routeIs('accounts.impersonate', 'backups.download')) {
            return back()->with('error', 'This action is unavailable in the limited public demo.');
        }

        if ($request->isMethodSafe()) {
            return $next($request);
        }

        if ($request->is('login') || $request->routeIs('logout', 'demo.login')) {
            return $next($request);
        }

        $message = 'Demo only: action simulated. No server or stored data was changed.';

        if ($request->header('X-Inertia')) {
            return back()->with('success', $message);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'demo' => true,
                'message' => $message,
                ...$request->only(['sortBy']),
            ]);
        }

        return back()->with('success', $message);
    }
}
