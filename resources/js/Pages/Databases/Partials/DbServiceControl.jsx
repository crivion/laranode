import { useState, useEffect } from 'react';
import axios from 'axios';
import OperationProgress from '@/Components/OperationProgress';

export default function DbServiceControl() {
    const [statuses, setStatuses] = useState({});
    const [operationId, setOperationId] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const fetchStatuses = () => {
        axios.get(route('databases.service.status')).then((r) => {
            setStatuses(r.data.statuses ?? {});
        });
    };

    useEffect(() => {
        fetchStatuses();
    }, []);

    const doAction = async (engine, action) => {
        try {
            setLoading(true);
            setError(null);
            const r = await axios.post(route('databases.service.action'), { engine, action });
            setOperationId(r.data.operation_id);
        } catch (err) {
            setLoading(false);
            setError(err.response?.data?.message ?? 'Request failed.');
        }
    };

    const handleDone = () => {
        setLoading(false);
        fetchStatuses();
    };

    return (
        <div className="max-w-7xl px-4 mt-8">
            <h3 className="font-semibold text-lg text-gray-800 dark:text-gray-200 mb-3">
                DB Service Control
            </h3>

            {error && (
                <div className="mb-3 text-sm text-red-600 dark:text-red-400">{error}</div>
            )}

            <div className="relative overflow-x-auto bg-white dark:bg-gray-850">
                <table className="w-full text-left rtl:text-right text-gray-500 dark:text-gray-400">
                    <thead className="text-gray-700 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-300 text-sm">
                        <tr>
                            <th className="px-6 py-3">Engine</th>
                            <th className="px-6 py-3">Service</th>
                            <th className="px-6 py-3">Status</th>
                            <th className="px-6 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="text-sm">
                        {Object.entries(statuses).map(([engine, info]) => (
                            <tr
                                key={engine}
                                className="bg-white border-b text-gray-700 dark:text-gray-200 dark:bg-gray-850 dark:border-gray-700 border-gray-200"
                            >
                                <td className="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                    {engine}
                                </td>
                                <td className="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                    {info.service}
                                </td>
                                <td className="px-6 py-4">
                                    {info.active ? (
                                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            active
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            inactive
                                        </span>
                                    )}
                                </td>
                                <td className="px-6 py-4">
                                    <div className="flex items-center space-x-2">
                                        {['start', 'stop', 'restart'].map((action) => (
                                            <button
                                                key={action}
                                                disabled={loading}
                                                onClick={() => doAction(engine, action)}
                                                className="px-3 py-1 text-xs font-medium rounded bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed capitalize"
                                            >
                                                {action}
                                            </button>
                                        ))}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {operationId !== null && (
                <OperationProgress operationId={operationId} onDone={handleDone} />
            )}
        </div>
    );
}
