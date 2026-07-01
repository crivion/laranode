import { useEffect, useRef } from 'react';
import useOperation from '@/hooks/useOperation';

const badge = { queued: 'text-gray-500', running: 'text-blue-600', succeeded: 'text-green-600', failed: 'text-red-600' };

export default function OperationProgress({ operationId, onDone }) {
    const { status, lines, exitCode } = useOperation(operationId);
    const firedRef = useRef(false);

    useEffect(() => { firedRef.current = false; }, [operationId]);

    useEffect(() => {
        if (!firedRef.current && (status === 'succeeded' || status === 'failed') && onDone) {
            firedRef.current = true;
            onDone(status);
        }
    }, [status, onDone]);

    return (
        <div className="mt-2">
            <div className={`text-sm font-medium ${badge[status] ?? ''}`}>Status: {status}{exitCode !== null ? ` (exit ${exitCode})` : ''}</div>
            <pre className="mt-1 whitespace-pre-wrap bg-black text-green-300 p-2 rounded max-h-64 overflow-auto text-xs">{lines.join('\n') || '…'}</pre>
        </div>
    );
}
