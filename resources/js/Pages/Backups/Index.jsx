import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ConfirmationButton from '@/Components/ConfirmationButton';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'react-toastify';
import { TbArchive } from 'react-icons/tb';
import { TiDelete } from 'react-icons/ti';
import CreateBackupForm from './Partials/CreateBackupForm';
import RestoreBackupModal from './Partials/RestoreBackupModal';
import BackupManifest from './Partials/BackupManifest';
import ScheduleForm from './Partials/ScheduleForm';

export default function BackupsIndex({ backups = [], schedules = [], websites = [], databases = [], users = [], destinations = [], showSnapshots = false }) {
    const { auth } = usePage().props;
    const active = backups.some(backup => ['pending', 'running'].includes(backup.status));
    useEffect(() => {
        if (!active) return;
        const timer = setInterval(async () => {
            const response = await window.axios.get(route('backups.statuses'));
            if (response.data.some(row => ['pending', 'running'].includes(row.status)) || active) router.reload({ only: ['backups'], preserveScroll: true });
        }, 3000);
        return () => clearInterval(timer);
    }, [active]);
    const remove = id => router.delete(route('backups.destroy', id), { preserveScroll: true, onError: () => toast.error('Unable to delete backup.') });
    return <AuthenticatedLayout header={<div className="flex max-w-7xl items-center justify-between pr-5"><h2 className="flex items-center text-xl font-semibold text-gray-800 dark:text-gray-200"><TbArchive className="mr-2" />Backups</h2><div className="flex gap-5"><ScheduleForm {...{ websites, databases, destinations, users }} currentUser={auth.user} /><CreateBackupForm {...{ websites, databases, destinations, users }} currentUser={auth.user} /></div></div>}>
        <Head title="Backups" />
        <div className="max-w-7xl px-4 my-8 space-y-8 text-gray-700 dark:text-gray-200">
            <div className="flex justify-end"><Link className="text-sm text-indigo-600 hover:underline" href={route('backups.index', { snapshots: showSnapshots ? 0 : 1 })}>{showSnapshots ? 'Hide' : 'Show'} safety snapshots</Link></div>
            <div className="overflow-x-auto bg-white dark:bg-gray-850"><table className="w-full text-left text-sm"><thead className="uppercase bg-gray-200 dark:bg-gray-700"><tr><th className="px-4 py-3">Created</th><th className="px-4">Account</th><th className="px-4">Scope</th><th className="px-4">Kind</th><th className="px-4">Status</th><th className="px-4">Size</th><th className="px-4">Actions</th></tr></thead><tbody>
                {backups.map(backup => <tr key={backup.id} className="border-b dark:border-gray-700"><td className="px-4 py-3 whitespace-nowrap">{new Date(backup.created_at).toLocaleString()}</td><td className="px-4">{backup.user?.username}</td><td className="px-4">{backup.scope.replaceAll('_', ' ')}</td><td className="px-4">{backup.kind.replaceAll('_', ' ')}</td><td className="px-4"><span className={backup.status === 'failed' ? 'text-red-600' : backup.status === 'completed' ? 'text-green-600' : 'text-amber-600'}>{backup.status}</span></td><td className="px-4">{backup.human_size}</td><td className="px-4 py-3"><div className="flex flex-wrap items-center gap-3"><BackupManifest backup={backup} />{backup.status === 'completed' && <a className="text-indigo-600 hover:underline" href={route('backups.download', backup.id)}>Download</a>}{backup.can_attempt_restore && <RestoreBackupModal backup={backup} />}{backup.status === 'failed' && backup.manifest && backup.restore_attempts_remaining === 0 && <span className="text-xs text-gray-500">Restore limit reached</span>} {!['pending','running'].includes(backup.status) && <ConfirmationButton doAction={() => remove(backup.id)}><TiDelete className="h-5 w-5 text-red-500" /></ConfirmationButton>}</div></td></tr>)}
                {backups.length === 0 && <tr><td colSpan="7" className="px-4 py-8 text-center text-gray-500">No backups yet.</td></tr>}
            </tbody></table></div>
            <section><h3 className="mb-3 text-lg font-semibold">Schedules</h3><div className="overflow-x-auto bg-white dark:bg-gray-850"><table className="w-full text-left text-sm"><thead className="bg-gray-200 uppercase dark:bg-gray-700"><tr><th className="px-4 py-3">Scope</th><th className="px-4">Frequency</th><th className="px-4">Next run</th><th className="px-4">Retention</th><th className="px-4">Actions</th></tr></thead><tbody>{schedules.map(s => <tr key={s.id} className="border-b dark:border-gray-700"><td className="px-4 py-3">{s.scope.replaceAll('_',' ')}</td><td className="px-4">{s.frequency} at {s.run_at}</td><td className="px-4">{s.next_run_at ? new Date(s.next_run_at).toLocaleString() : '—'}</td><td className="px-4">{s.retention_count}</td><td className="px-4"><ConfirmationButton doAction={() => router.delete(route('backup-schedules.destroy', s.id), { preserveScroll: true })}><TiDelete className="h-5 w-5 text-red-500" /></ConfirmationButton></td></tr>)}</tbody></table></div></section>
        </div>
    </AuthenticatedLayout>;
}
