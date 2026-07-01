<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferencesController extends Controller
{
    private const KNOWN_EVENT_TYPES = [
        'operation.finished',
        'operation.failed',
        'ssl.expiring',
        'ssl.issued',
        'backup.result',
        'fail2ban.ban',
        'resource.threshold',
        'deploy.success',
        'deploy.failed',
    ];

    private const CHANNELS = ['database', 'mail', 'webhook'];

    public function index(Request $request): Response
    {
        $user = $request->user();

        $preferences = NotificationPreference::where('user_id', $user->id)
            ->get(['event_type', 'channel', 'enabled'])
            ->toArray();

        return Inertia::render('Profile/Notifications', [
            'eventTypes' => self::KNOWN_EVENT_TYPES,
            'channels' => self::CHANNELS,
            'preferences' => $preferences,
            'webhookUrl' => $user->webhook_url,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => ['required', 'string', 'in:'.implode(',', self::KNOWN_EVENT_TYPES)],
            'channel' => ['required', 'string', 'in:'.implode(',', self::CHANNELS)],
            'enabled' => ['required', 'boolean'],
        ]);

        NotificationPreference::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'event_type' => $validated['event_type'],
                'channel' => $validated['channel'],
            ],
            ['enabled' => $validated['enabled']]
        );

        return response()->json(['success' => true]);
    }

    public function updateWebhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'webhook_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $url = $validated['webhook_url'];

        if ($url !== null) {
            $parsed = parse_url($url);
            $scheme = $parsed['scheme'] ?? '';

            if (! in_array($scheme, ['http', 'https'], true)) {
                throw ValidationException::withMessages([
                    'webhook_url' => ['The webhook URL must use the http or https scheme.'],
                ]);
            }

            $host = $parsed['host'] ?? '';

            // Strip IPv6 brackets
            if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
                $host = substr($host, 1, -1);
            }

            $ip = gethostbyname($host);

            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw ValidationException::withMessages([
                    'webhook_url' => ['The webhook URL resolves to a private or reserved IP address.'],
                ]);
            }
        }

        $request->user()->update(['webhook_url' => $url]);

        return response()->json(['success' => true]);
    }
}
