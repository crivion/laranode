import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function RestoreBackupModal({ backup }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ confirm: '' });
    const submit = e => { e.preventDefault(); post(route('backups.restore', backup.id), { onSuccess: () => { setOpen(false); reset(); } }); };
    return <>
        <button onClick={() => setOpen(true)} className="text-amber-600 hover:underline">{backup.status === 'failed' ? 'Retry restore' : 'Restore'}</button>
        <Modal show={open} onClose={() => setOpen(false)}>
            <form onSubmit={submit} className="p-6 space-y-4 text-gray-800 dark:text-gray-200">
                <h2 className="text-lg font-medium">Overwrite current data?</h2>
                <p className="text-sm">A safety snapshot is created first. Database restore is not atomic. Type <strong>{backup.confirmation_name}</strong> to continue.</p>
                {backup.status === 'failed' && <p className="rounded bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                    {backup.restore_attempts_remaining} of 3 retry attempts remaining. Submitting this retry will use one attempt.
                </p>}
                <TextInput className="w-full" value={data.confirm} onChange={e => setData('confirm', e.target.value)} autoFocus />
                <InputError message={errors.confirm} />
                <div className="flex justify-end gap-3"><SecondaryButton type="button" onClick={() => setOpen(false)}>Cancel</SecondaryButton><PrimaryButton disabled={processing || data.confirm !== backup.confirmation_name}>Restore</PrimaryButton></div>
            </form>
        </Modal>
    </>;
}
