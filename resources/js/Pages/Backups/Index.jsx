import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { MdBackup } from 'react-icons/md';
import { TiDelete } from 'react-icons/ti';
import { toast } from 'react-toastify';
import { useState } from 'react';
import axios from 'axios';
import OperationProgress from '@/Components/OperationProgress';

export default function BackupsIndex({ backups = { data: [] }, schedules = [] }) {
    const { auth } = usePage().props;

    // On-demand backup form state
    const [form, setForm] = useState({ type: 'db', target: '', storage: 'local' });
    const [activeOp, setActiveOp] = useState(null);

    // Restore modal state
    const [restoreModal, setRestoreModal] = useState(null); // { backup }
    const [newTarget, setNewTarget] = useState('');
    const [restoreOp, setRestoreOp] = useState(null);

    const handleFormChange = (e) => setForm((f) => ({ ...f, [e.target.name]: e.target.value }));

    const submitBackup = (e) => {
        e.preventDefault();
        axios.post(route('backups.store'), form)
            .then((res) => setActiveOp(res.data.operation_id))
            .catch(() => toast.error('Failed to start backup'));
    };

    const deleteBackup = (backup) => {
        router.delete(route('backups.destroy', { backup: backup.id }), {
            onBefore: () => toast('Deleting backup…'),
            onError: () => toast.error('Failed to delete backup'),
        });
    };

    const openRestoreModal = (backup) => {
        setRestoreModal({ backup });
        setNewTarget('');
        setRestoreOp(null);
    };

    const closeRestoreModal = () => {
        setRestoreModal(null);
        setRestoreOp(null);
        setNewTarget('');
    };

    const submitRestore = (e) => {
        e.preventDefault();
        axios.post(route('backups.restore', { backup: restoreModal.backup.id }), { new_target: newTarget })
            .then((res) => setRestoreOp(res.data.operation_id))
            .catch((err) => {
                const msg = err.response?.data?.errors?.new_target?.[0] ?? 'Failed to start restore';
                toast.error(msg);
            });
    };

    const deleteSchedule = (schedule) => {
        router.delete(route('backups.schedules.destroy', { scheduledBackup: schedule.id }), {
            onBefore: () => toast('Deleting schedule…'),
            onError: () => toast.error('Failed to delete schedule'),
        });
    };

    const backupRows = backups.data ?? [];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col xl:flex-row xl:justify-between max-w-7xl pr-5">
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                        <MdBackup className="mr-2" />
                        Backups
                    </h2>
                </div>
            }
        >
            <Head title="Backups" />

            <div className="max-w-7xl px-4 my-8 space-y-10">

                {/* On-demand backup form */}
                <section>
                    <h3 className="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-3">Create On-Demand Backup</h3>
                    <form onSubmit={submitBackup} className="flex flex-wrap gap-3 items-end">
                        <div>
                            <label htmlFor="backup-type" className="block text-sm text-gray-600 dark:text-gray-400 mb-1">Type</label>
                            <select
                                id="backup-type"
                                name="type"
                                value={form.type}
                                onChange={handleFormChange}
                                className="border border-gray-300 rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                            >
                                <option value="db">Database</option>
                                <option value="files">Files</option>
                            </select>
                        </div>
                        <div>
                            <label htmlFor="backup-target" className="block text-sm text-gray-600 dark:text-gray-400 mb-1">Target</label>
                            <input
                                id="backup-target"
                                name="target"
                                type="text"
                                value={form.target}
                                onChange={handleFormChange}
                                placeholder={form.type === 'db' ? 'database name' : 'site URL'}
                                className="border border-gray-300 rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                            />
                        </div>
                        <div>
                            <label htmlFor="backup-storage" className="block text-sm text-gray-600 dark:text-gray-400 mb-1">Storage</label>
                            <select
                                id="backup-storage"
                                name="storage"
                                value={form.storage}
                                onChange={handleFormChange}
                                className="border border-gray-300 rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                            >
                                <option value="local">Local</option>
                                <option value="s3">S3</option>
                            </select>
                        </div>
                        <button
                            type="submit"
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2 rounded"
                        >
                            Run Backup
                        </button>
                    </form>

                    {activeOp && (
                        <div className="mt-4 p-3 border rounded dark:border-gray-700">
                            <div className="text-sm text-gray-600 dark:text-gray-400">Backup in progress…</div>
                            <OperationProgress
                                operationId={activeOp}
                                onDone={() => { setActiveOp(null); router.reload(); }}
                            />
                        </div>
                    )}
                </section>

                {/* Backup rows table */}
                <section>
                    <h3 className="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-3">Backup History</h3>
                    {backupRows.length === 0 ? (
                        <p className="text-gray-500 dark:text-gray-400">No backups found.</p>
                    ) : (
                        <div className="relative overflow-x-auto bg-white dark:bg-gray-850">
                            <table className="w-full text-left text-gray-500 dark:text-gray-400 text-sm">
                                <thead className="text-gray-700 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-300">
                                    <tr>
                                        <th className="px-6 py-3">Type</th>
                                        <th className="px-6 py-3">Target</th>
                                        <th className="px-6 py-3">Storage</th>
                                        <th className="px-6 py-3">Status</th>
                                        <th className="px-6 py-3">Size</th>
                                        <th className="px-6 py-3">Created</th>
                                        <th className="px-6 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {backupRows.map((b) => (
                                        <tr key={`backup-${b.id}`} className="bg-white border-b text-gray-700 dark:text-gray-200 dark:bg-gray-850 dark:border-gray-700 border-gray-200">
                                            <td className="px-6 py-4 font-medium whitespace-nowrap">{b.type}</td>
                                            <td className="px-6 py-4 whitespace-nowrap">{b.target}</td>
                                            <td className="px-6 py-4 whitespace-nowrap">{b.storage}</td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`px-2 py-0.5 rounded text-xs font-medium ${
                                                    b.status === 'completed'
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                        : b.status === 'failed'
                                                        ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                        : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                }`}>
                                                    {b.status}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                {b.size_bytes ? `${(b.size_bytes / 1024 / 1024).toFixed(2)} MB` : '—'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">{b.created_at}</td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center space-x-2">
                                                    {b.status === 'completed' && (
                                                        <>
                                                            <a
                                                                href={route('backups.download', b)}
                                                                className="text-blue-600 hover:underline text-xs"
                                                            >
                                                                Download
                                                            </a>
                                                            {b.type === 'db' && (
                                                                <button
                                                                    onClick={() => openRestoreModal(b)}
                                                                    className="text-indigo-600 hover:underline text-xs"
                                                                >
                                                                    Restore
                                                                </button>
                                                            )}
                                                        </>
                                                    )}
                                                    <button
                                                        onClick={() => deleteBackup(b)}
                                                        className="text-red-500 hover:text-red-700"
                                                        title="Delete backup"
                                                    >
                                                        <TiDelete className="w-5 h-5" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                {/* Scheduled backups table */}
                <section>
                    <h3 className="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-3">Scheduled Backups</h3>
                    {schedules.length === 0 ? (
                        <p className="text-gray-500 dark:text-gray-400">No scheduled backups configured.</p>
                    ) : (
                        <div className="relative overflow-x-auto bg-white dark:bg-gray-850">
                            <table className="w-full text-left text-gray-500 dark:text-gray-400 text-sm">
                                <thead className="text-gray-700 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-300">
                                    <tr>
                                        <th className="px-6 py-3">Type</th>
                                        <th className="px-6 py-3">Target</th>
                                        <th className="px-6 py-3">Cron</th>
                                        <th className="px-6 py-3">Keep</th>
                                        <th className="px-6 py-3">Last Run</th>
                                        <th className="px-6 py-3">Enabled</th>
                                        <th className="px-6 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {schedules.map((s) => (
                                        <tr key={`schedule-${s.id}`} className="bg-white border-b text-gray-700 dark:text-gray-200 dark:bg-gray-850 dark:border-gray-700 border-gray-200">
                                            <td className="px-6 py-4 whitespace-nowrap">{s.type}</td>
                                            <td className="px-6 py-4 whitespace-nowrap">{s.target}</td>
                                            <td className="px-6 py-4 whitespace-nowrap font-mono text-xs">{s.cron_expression}</td>
                                            <td className="px-6 py-4 whitespace-nowrap">{s.retention_count}</td>
                                            <td className="px-6 py-4 whitespace-nowrap">{s.last_run_at ?? '—'}</td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`px-2 py-0.5 rounded text-xs font-medium ${s.enabled ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400'}`}>
                                                    {s.enabled ? 'Yes' : 'No'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <button
                                                    onClick={() => deleteSchedule(s)}
                                                    className="text-red-500 hover:text-red-700"
                                                    title="Delete schedule"
                                                >
                                                    <TiDelete className="w-5 h-5" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>

            {/* Restore modal */}
            {restoreModal && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md">
                        <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                            Restore Backup: {restoreModal.backup.target}
                        </h3>

                        <p className="text-sm text-yellow-700 dark:text-yellow-400 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded p-3 mb-4">
                            The original is not touched — a new database will be created with the name you provide below.
                        </p>

                        {restoreOp ? (
                            <div>
                                <OperationProgress
                                    operationId={restoreOp}
                                    onDone={(status) => {
                                        if (status === 'succeeded') {
                                            toast.success('Restore completed');
                                            closeRestoreModal();
                                            router.reload();
                                        }
                                    }}
                                />
                                <button
                                    onClick={closeRestoreModal}
                                    className="mt-3 text-sm text-gray-500 hover:underline"
                                >
                                    Close
                                </button>
                            </div>
                        ) : (
                            <form onSubmit={submitRestore}>
                                <div className="mb-4">
                                    <label htmlFor="new_target" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        New Target Name
                                    </label>
                                    <input
                                        id="new_target"
                                        type="text"
                                        value={newTarget}
                                        onChange={(e) => setNewTarget(e.target.value)}
                                        placeholder="e.g. mydb_restored"
                                        className="w-full border border-gray-300 rounded px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                                    />
                                </div>
                                <div className="flex space-x-3">
                                    <button
                                        type="submit"
                                        className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2 rounded"
                                    >
                                        Start Restore
                                    </button>
                                    <button
                                        type="button"
                                        onClick={closeRestoreModal}
                                        className="bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm px-4 py-2 rounded"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
