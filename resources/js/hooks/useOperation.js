import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';

export default function useOperation(operationId) {
    const userId = usePage().props.auth.user.id;
    const [status, setStatus] = useState('queued');
    const [lines, setLines] = useState([]);
    const [exitCode, setExitCode] = useState(null);

    useEffect(() => {
        if (!operationId) return;
        setStatus('queued'); setLines([]); setExitCode(null);

        const channel = window.Echo.private(`operations.${userId}`);
        channel.listen('.OperationUpdated', (e) => {
            if (e.operationId !== operationId) return;
            if (e.kind === 'line' && e.line) setLines((prev) => [...prev, e.line]);
            if (e.kind === 'status') { setStatus(e.status); setExitCode(e.exitCode); }
        });

        return () => channel.stopListening('.OperationUpdated');
    }, [operationId, userId]);

    return { status, lines, exitCode };
}
