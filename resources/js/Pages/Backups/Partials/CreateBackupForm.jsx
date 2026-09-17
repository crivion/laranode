import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import InputError from '@/Components/InputError';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function CreateBackupForm({ websites, databases, destinations, users, currentUser }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ scope: 'account', user_id: currentUser.id, website_id: '', database_id: '', destination_id: '' });
    const targetUser = Number(data.user_id || currentUser.id);
    const submit = (event) => { event.preventDefault(); post(route('backups.store'), { onSuccess: () => { setOpen(false); reset(); } }); };
    const input = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200';
    return <>
        <button onClick={() => setOpen(true)} className="text-gray-700 dark:text-gray-300">Create backup</button>
        <Modal show={open} onClose={() => setOpen(false)}>
            <form onSubmit={submit} className="p-6 space-y-4 text-gray-800 dark:text-gray-200">
                <h2 className="text-lg font-medium">Create backup</h2>
                {users.length > 0 && <div><label>Account</label><select className={input} value={data.user_id} onChange={e => setData('user_id', e.target.value)}>{users.map(u => <option key={u.id} value={u.id}>{u.username}</option>)}</select></div>}
                <div><label>Scope</label><select className={input} value={data.scope} onChange={e => setData('scope', e.target.value)}><option value="account">Whole account</option><option value="website_files">Website files</option><option value="website_database">Database</option></select><InputError message={errors.scope} /></div>
                {data.scope === 'website_files' && <div><label>Website</label><select className={input} value={data.website_id} onChange={e => setData('website_id', e.target.value)}><option value="">Select…</option>{websites.filter(w => w.user_id === targetUser).map(w => <option key={w.id} value={w.id}>{w.url}</option>)}</select><InputError message={errors.website_id} /></div>}
                {data.scope === 'website_database' && <div><label>Database</label><select className={input} value={data.database_id} onChange={e => setData('database_id', e.target.value)}><option value="">Select…</option>{databases.filter(d => d.user_id === targetUser).map(d => <option key={d.id} value={d.id}>{d.name}</option>)}</select><InputError message={errors.database_id} /></div>}
                <div><label>Destination</label><select className={input} value={data.destination_id} onChange={e => setData('destination_id', e.target.value)}><option value="">Default (local if not configured)</option>{destinations.map(d => <option key={d.id} value={d.id}>{d.name} ({d.driver})</option>)}</select><InputError message={errors.destination_id} /></div>
                <div className="flex justify-end gap-3"><SecondaryButton type="button" onClick={() => setOpen(false)}>Cancel</SecondaryButton><PrimaryButton disabled={processing}>Queue backup</PrimaryButton></div>
            </form>
        </Modal>
    </>;
}
