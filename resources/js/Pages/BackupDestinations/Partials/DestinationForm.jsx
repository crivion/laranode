import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import InputError from '@/Components/InputError';
import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function DestinationForm({ destination = null }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, put, processing, errors, reset } = useForm({ name: destination?.name || '', driver: destination?.driver || 'local', is_default: destination?.is_default || false, config: destination?.config || {} });
    useEffect(() => { if (!open) reset(); }, [open]);
    const config = (key, value) => setData('config', { ...data.config, [key]: value });
    const submit = e => { e.preventDefault(); const options = { onSuccess: () => setOpen(false) }; destination ? put(route('backup-destinations.update', destination.id), options) : post(route('backup-destinations.store'), options); };
    const input = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200';
    const s3Fields = [
        ['key', 'Access key', 'Spaces access key'],
        ['secret', 'Secret key', destination ? 'Leave blank to keep current' : 'Spaces secret key'],
        ['region', 'Region', 'fra1'],
        ['bucket', 'Bucket', 'Bucket name'],
        ['endpoint', 'Endpoint (optional)', 'https://fra1.digitaloceanspaces.com'],
    ];
    const sftpFields = [
        ['host', 'Host', 'sftp.example.com'],
        ['port', 'Port', '22'],
        ['username', 'Username', 'backup-user'],
        ['password', 'Password', destination ? 'Leave blank to keep current' : 'Password'],
        ['privateKey', 'Private key (optional)', destination ? 'Leave blank to keep current' : 'Paste a private key instead of a password'],
        ['root', 'Remote root (optional)', 'Leave blank to use the SSH user home'],
    ];

    return <><button className="text-indigo-600 hover:underline" onClick={() => setOpen(true)}>{destination ? 'Edit' : 'Add destination'}</button><Modal show={open} onClose={() => setOpen(false)}><form onSubmit={submit} className="p-6 space-y-3 text-gray-800 dark:text-gray-200"><h2 className="text-lg font-medium">{destination ? 'Edit' : 'Add'} backup destination</h2>
        <input className={input} placeholder="Name" value={data.name} onChange={e => setData('name', e.target.value)} />
        <select className={input} value={data.driver} onChange={e => setData('driver', e.target.value)}><option value="local">Local</option><option value="s3">S3 compatible</option><option value="sftp">SFTP</option></select>
        {data.driver === 's3' && <div className="space-y-3">{s3Fields.map(([key, label, placeholder]) => <label key={key} className="block text-sm font-medium">{label}<input className={input} type={key === 'secret' ? 'password' : 'text'} value={data.config[key] || ''} placeholder={placeholder} onChange={e => config(key, e.target.value)} /></label>)}<label className="flex gap-2"><input type="checkbox" checked={!!data.config.use_path_style_endpoint} onChange={e => config('use_path_style_endpoint', e.target.checked)} />Use path-style endpoint</label></div>}
        {data.driver === 'sftp' && <div className="space-y-3">{sftpFields.map(([key, label, placeholder]) => <label key={key} className="block text-sm font-medium">{label}<input className={input} type={key === 'password' ? 'password' : 'text'} inputMode={key === 'port' ? 'numeric' : undefined} value={data.config[key] || ''} placeholder={placeholder} onChange={e => config(key, e.target.value)} /></label>)}</div>}
        <label className="flex gap-2"><input type="checkbox" checked={data.is_default} onChange={e => setData('is_default', e.target.checked)} />Default destination</label>
        {Object.values(errors).map((error, i) => <InputError key={i} message={error} />)}
        <div className="flex justify-end gap-3"><SecondaryButton type="button" onClick={() => setOpen(false)}>Cancel</SecondaryButton><PrimaryButton disabled={processing}>Save</PrimaryButton></div>
    </form></Modal></>;
}
