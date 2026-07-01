import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import useNotifications from '@/hooks/useNotifications';

export default function Index({ notifications }) {
    const { refresh } = useNotifications();
    const [markingAll, setMarkingAll] = useState(false);

    const handleMarkAllRead = async () => {
        setMarkingAll(true);
        try {
            await fetch('/notifications/read-all', {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'Content-Type': 'application/json',
                },
            });
            refresh();
            router.reload({ only: ['notifications'] });
        } finally {
            setMarkingAll(false);
        }
    };

    const handleMarkRead = async (id) => {
        await fetch(`/notifications/${id}/read`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Content-Type': 'application/json',
            },
        });
        router.reload({ only: ['notifications'] });
    };

    const unreadCount = notifications.filter((n) => n.read_at === null).length;

    return (
        <AuthenticatedLayout>
            <Head title="Notifications" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-4">
                    <h1 className="text-xl font-semibold">Notifications</h1>
                    {unreadCount > 0 && (
                        <button
                            onClick={handleMarkAllRead}
                            disabled={markingAll}
                            className="text-sm text-indigo-600 hover:underline disabled:opacity-50"
                        >
                            Mark all as read
                        </button>
                    )}
                </div>

                {notifications.length === 0 ? (
                    <p className="text-gray-500 text-sm">No notifications yet.</p>
                ) : (
                    <ul className="space-y-2">
                        {notifications.map((notification) => (
                            <li
                                key={notification.id}
                                className={`p-4 rounded border ${notification.read_at ? 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700' : 'bg-indigo-50 dark:bg-indigo-950 border-indigo-200 dark:border-indigo-700'}`}
                            >
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="text-sm font-medium">
                                            {notification.data?.title ?? notification.type}
                                        </p>
                                        {notification.data?.message && (
                                            <p className="text-xs text-gray-500 mt-1">
                                                {notification.data.message}
                                            </p>
                                        )}
                                        <p className="text-xs text-gray-400 mt-1">
                                            {notification.created_at}
                                        </p>
                                    </div>
                                    {!notification.read_at && (
                                        <button
                                            onClick={() => handleMarkRead(notification.id)}
                                            className="text-xs text-indigo-600 hover:underline ml-4 shrink-0"
                                        >
                                            Mark read
                                        </button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
