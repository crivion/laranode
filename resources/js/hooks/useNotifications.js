import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';

export default function useNotifications() {
    const { auth, notifications } = usePage().props;
    const userId = auth.user.id;
    const [unreadCount, setUnreadCount] = useState(notifications?.unreadCount ?? 0);

    useEffect(() => {
        const channel = window.Echo.private('notifications.' + userId);
        channel.listen('.NotificationCreated', (e) => {
            setUnreadCount(e.unread_count);
        });
        return () => channel.stopListening('.NotificationCreated');
    }, [userId]);

    function refresh() {
        setUnreadCount(0);
    }

    return { unreadCount, refresh };
}
