import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import InputError from '@/Components/InputError';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function ScheduleForm({ websites, databases, destinations, users, currentUser }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ scope: 'account', user_id: currentUser.id, website_id: '', database_id: '', destination_id: '', frequency: 'daily', run_at: '02:00', day_of_week: 1, day_of_month: 1, retention_count: 7, enabled: true });
    const uid = Number(data.user_id || currentUser.id); const input = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200';
    const submit = e => { e.preventDefault(); post(route('backup-schedules.store'), { onSuccess: () => { setOpen(false); reset(); } }); };
    return <>
        <button onClick={() => setOpen(true)} className="text-gray-700 dark:text-gray-300">Add schedule</button>
        <Modal show={open} onClose={() => setOpen(false)}><form onSubmit={submit} className="p-6 space-y-3 text-gray-800 dark:text-gray-200"><h2 className="text-lg font-medium">Backup schedule</h2>
            {users.length > 0 && <select className={input} value={data.user_id} onChange={e => setData('user_id', e.target.value)}>{users.map(u => <option key={u.id} value={u.id}>{u.username}</option>)}</select>}
            <select className={input} value={data.scope} onChange={e => setData('scope', e.target.value)}><option value="account">Whole account</option><option value="website_files">Website files</option><option value="website_database">Database</option></select>
            {data.scope === 'website_files' && <select className={input} value={data.website_id} onChange={e => setData('website_id', e.target.value)}><option value="">Select website…</option>{websites.filter(w => w.user_id === uid).map(w => <option key={w.id} value={w.id}>{w.url}</option>)}</select>}
            {data.scope === 'website_database' && <select className={input} value={data.database_id} onChange={e => setData('database_id', e.target.value)}><option value="">Select database…</option>{databases.filter(d => d.user_id === uid).map(d => <option key={d.id} value={d.id}>{d.name}</option>)}</select>}
            <select className={input} value={data.destination_id} onChange={e => setData('destination_id', e.target.value)}><option value="">Default (local if not configured)</option>{destinations.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}</select>
            <div className="grid grid-cols-2 gap-3"><select className={input} value={data.frequency} onChange={e => setData('frequency', e.target.value)}><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select><input className={input} type="time" value={data.run_at} onChange={e => setData('run_at', e.target.value)} /></div>
            {data.frequency === 'weekly' && <select className={input} value={data.day_of_week} onChange={e => setData('day_of_week', e.target.value)}>{['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'].map((d, i) => <option key={d} value={i}>{d}</option>)}</select>}
            {data.frequency === 'monthly' && <input className={input} type="number" min="1" max="31" value={data.day_of_month} onChange={e => setData('day_of_month', e.target.value)} />}
            <label className="block">Keep latest <input className={input} type="number" min="1" value={data.retention_count} onChange={e => setData('retention_count', e.target.value)} /></label>
            {Object.values(errors).map((error, i) => <InputError key={i} message={error} />)}
            <div className="flex justify-end gap-3"><SecondaryButton type="button" onClick={() => setOpen(false)}>Cancel</SecondaryButton><PrimaryButton disabled={processing}>Save schedule</PrimaryButton></div>
        </form></Modal>
    </>;
}
