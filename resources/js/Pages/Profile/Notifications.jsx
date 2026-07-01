import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';

const CHANNEL_LABELS = {
    database: 'In-app',
    mail: 'Email',
    webhook: 'Webhook',
};

const EVENT_LABELS = {
    'operation.finished': 'Operation finished',
    'operation.failed': 'Operation failed',
    'ssl.expiring': 'SSL expiring',
    'ssl.issued': 'SSL issued',
    'backup.result': 'Backup result',
    'fail2ban.ban': 'Fail2ban ban',
    'resource.threshold': 'Resource threshold',
    'deploy.success': 'Deploy success',
    'deploy.failed': 'Deploy failed',
};

function buildMatrix(eventTypes, channels, preferences) {
    const matrix = {};
    for (const eventType of eventTypes) {
        matrix[eventType] = {};
        for (const channel of channels) {
            const row = preferences.find(
                (p) => p.event_type === eventType && p.channel === channel,
            );
            matrix[eventType][channel] = row ? Boolean(row.enabled) : true;
        }
    }
    return matrix;
}

export default function Notifications({ eventTypes, channels, preferences, webhookUrl }) {
    const [matrix, setMatrix] = useState(() => buildMatrix(eventTypes, channels, preferences));
    const [webhookInput, setWebhookInput] = useState(webhookUrl ?? '');
    const [webhookError, setWebhookError] = useState('');
    const [webhookSaved, setWebhookSaved] = useState(false);

    const handleToggle = async (eventType, channel, enabled) => {
        setMatrix((prev) => ({
            ...prev,
            [eventType]: { ...prev[eventType], [channel]: enabled },
        }));
        try {
            await axios.patch('/profile/notifications', { event_type: eventType, channel, enabled });
        } catch {
            // Revert on failure
            setMatrix((prev) => ({
                ...prev,
                [eventType]: { ...prev[eventType], [channel]: !enabled },
            }));
        }
    };

    const handleWebhookSave = async (e) => {
        e.preventDefault();
        setWebhookError('');
        setWebhookSaved(false);
        try {
            await axios.patch('/profile/notifications/webhook', {
                webhook_url: webhookInput || null,
            });
            setWebhookSaved(true);
        } catch (err) {
            const msg =
                err?.response?.data?.errors?.webhook_url?.[0] ??
                'Failed to save webhook URL.';
            setWebhookError(msg);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Notification Preferences" />
            <div className="p-6 max-w-4xl">
                <h1 className="text-xl font-semibold mb-6 text-gray-900 dark:text-gray-100">Notification Preferences</h1>

                {/* Preference matrix */}
                <div className="bg-white dark:bg-gray-950 shadow rounded-lg p-4 mb-6 overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr>
                                <th className="text-left py-2 pr-4 font-medium text-gray-700 dark:text-gray-300">Event</th>
                                {channels.map((ch) => (
                                    <th key={ch} className="text-center py-2 px-4 font-medium text-gray-700 dark:text-gray-300">
                                        {CHANNEL_LABELS[ch] ?? ch}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {eventTypes.map((eventType) => (
                                <tr key={eventType} className="border-t border-gray-200 dark:border-gray-700">
                                    <td className="py-2 pr-4 text-gray-700 dark:text-gray-300">
                                        {EVENT_LABELS[eventType] ?? eventType}
                                    </td>
                                    {channels.map((channel) => (
                                        <td key={channel} className="text-center py-2 px-4">
                                            <input
                                                type="checkbox"
                                                checked={matrix[eventType]?.[channel] ?? true}
                                                onChange={(e) =>
                                                    handleToggle(eventType, channel, e.target.checked)
                                                }
                                                className="w-4 h-4 accent-indigo-600"
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Webhook URL */}
                <div className="bg-white dark:bg-gray-950 shadow rounded-lg p-4">
                    <h2 className="text-base font-semibold mb-3 text-gray-900 dark:text-gray-100">Webhook URL</h2>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mb-3">
                        Notifications will be POSTed as JSON to this URL. Leave blank to disable.
                    </p>
                    <form onSubmit={handleWebhookSave} className="flex flex-col gap-2 max-w-xl">
                        <input
                            type="url"
                            value={webhookInput}
                            onChange={(e) => {
                                setWebhookInput(e.target.value);
                                setWebhookSaved(false);
                                setWebhookError('');
                            }}
                            placeholder="https://hooks.example.com/..."
                            className="border rounded px-3 py-2 text-sm dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200"
                        />
                        {webhookError && (
                            <p className="text-red-500 text-xs">{webhookError}</p>
                        )}
                        {webhookSaved && (
                            <p className="text-green-600 text-xs">Webhook URL saved.</p>
                        )}
                        <button
                            type="submit"
                            className="self-start px-4 py-2 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700"
                        >
                            Save
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
