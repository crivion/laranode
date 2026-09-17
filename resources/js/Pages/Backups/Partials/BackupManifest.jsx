import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import { useState } from 'react';

export default function BackupManifest({ backup }) {
    const [open, setOpen] = useState(false);
    if (!backup.manifest) return null;
    return <>
        <button className="text-indigo-600 hover:underline" onClick={() => setOpen(true)}>Details</button>
        <Modal show={open} onClose={() => setOpen(false)} maxWidth="lg">
            <div className="space-y-4 p-6 text-gray-800 dark:text-gray-200">
                <div>
                    <h2 className="text-lg font-medium">Backup details</h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {backup.user?.username} · {backup.scope.replaceAll('_', ' ')} · {new Date(backup.created_at).toLocaleString()}
                    </p>
                </div>
                <div className="rounded bg-gray-100 px-3 py-2 text-sm dark:bg-gray-900">
                    <span className="font-medium">Destination:</span>{' '}
                    {backup.destination ? `${backup.destination.name} (${backup.destination.driver.toUpperCase()})` : 'Local server'}
                </div>
                <div className="max-h-80 space-y-2 overflow-y-auto rounded bg-gray-100 p-3 text-sm dark:bg-gray-900">
                    {(backup.manifest.websites || []).map(site => <div key={site.url}>Files: {site.url} ({site.bytes?.toLocaleString()} bytes)</div>)}
                    {(backup.manifest.databases || []).map(db => <div key={db.name}>Database: {db.name} ({db.bytes?.toLocaleString()} bytes)</div>)}
                    {(backup.manifest.websites || []).length === 0 && (backup.manifest.databases || []).length === 0 && <div className="text-gray-500 dark:text-gray-400">No files or databases are listed in this backup.</div>}
                    {backup.error && <div className="text-red-600">{backup.error}</div>}
                </div>
                <div className="flex justify-end">
                    <SecondaryButton type="button" onClick={() => setOpen(false)}>Close</SecondaryButton>
                </div>
            </div>
        </Modal>
    </>;
}
