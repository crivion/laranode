import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ConfirmationButton from '@/Components/ConfirmationButton';
import { Head, router } from '@inertiajs/react';
import { TbCloudLock } from 'react-icons/tb';
import { TiDelete } from 'react-icons/ti';
import DestinationForm from './Partials/DestinationForm';
import { toast } from 'react-toastify';

export default function DestinationIndex({ destinations = [] }) {
    return <AuthenticatedLayout header={<div className="flex max-w-7xl items-center justify-between pr-5"><h2 className="flex items-center text-xl font-semibold text-gray-800 dark:text-gray-200"><TbCloudLock className="mr-2" />Backup destinations</h2><DestinationForm /></div>}><Head title="Backup Destinations" /><div className="max-w-7xl px-4 my-8"><div className="overflow-x-auto bg-white dark:bg-gray-850"><table className="w-full text-left text-sm text-gray-700 dark:text-gray-200"><thead className="bg-gray-200 uppercase dark:bg-gray-700"><tr><th className="px-5 py-3">Name</th><th className="px-5">Driver</th><th className="px-5">Default</th><th className="px-5">Actions</th></tr></thead><tbody>{destinations.map(d => <tr key={d.id} className="border-b dark:border-gray-700"><td className="px-5 py-4">{d.name}</td><td className="px-5">{d.driver}</td><td className="px-5">{d.is_default ? 'Yes' : 'No'}</td><td className="px-5"><div className="flex items-center gap-3"><DestinationForm destination={d} /><button className="text-green-600 hover:underline" onClick={() => router.post(route('backup-destinations.test', d.id), {}, { preserveScroll: true, onError: errors => toast.error(errors.destination || 'Connection failed.') })}>Test</button><ConfirmationButton doAction={() => router.delete(route('backup-destinations.destroy', d.id), { preserveScroll: true })}><TiDelete className="h-5 w-5 text-red-500" /></ConfirmationButton></div></td></tr>)}</tbody></table></div></div></AuthenticatedLayout>;
}
