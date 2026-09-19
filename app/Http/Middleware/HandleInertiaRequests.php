<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'isImpersonating' => app('impersonate')->isImpersonating(),
            ],
            'ssh' => [
                'host' => parse_url(config('app.url'), PHP_URL_HOST) ?: $request->getHost(),
                'port' => (int) config('laranode.ssh_port'),
            ],
            'demo' => [
                'enabled' => (bool) config('laranode.demo.enabled'),
                'message' => 'Limited public demo — actions are simulated. No server, files, firewall, backups, or external services are changed.',
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ];
    }
}
