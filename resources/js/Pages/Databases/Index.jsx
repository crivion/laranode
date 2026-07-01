import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { TbDatabase } from 'react-icons/tb';
import { TiDelete } from 'react-icons/ti';
import { toast } from 'react-toastify';
import CreateDatabaseForm from './Partials/CreateDatabaseForm';
import DbServiceControl from './Partials/DbServiceControl';
import EditDatabaseForm from './Partials/EditDatabaseForm';
import ConfirmationButton from '@/Components/ConfirmationButton';
import { Tooltip } from 'react-tooltip';

export default function DatabasesIndex({ databases = [] }) {

    const { auth } = usePage().props;

    const deleteDb = (id) => {
        router.delete(route('databases.destroy'), {
            data: { id },
            onBefore: () => toast('Deleting database...'),
            onError: () => toast('Failed to delete database.'),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col xl:flex-row xl:justify-between max-w-7xl pr-5">
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                        <TbDatabase className="mr-2" />
                        Databases ({databases.length}/{auth.user.database_limit || 'unlimited'})
                    </h2>
                    <CreateDatabaseForm />
                </div>
            }
        >
            <Head title="Databases" />

            <div className="max-w-7xl px-4 my-8">
                {databases.length === 0 ? (
                    <p className="text-gray-500 dark:text-gray-400">No databases found.</p>
                ) : (
                    <div className="relative overflow-x-auto bg-white dark:bg-gray-850 mt-3">
                        <table className="w-full text-left rtl:text-right text-gray-500 dark:text-gray-400">
                            <thead className="text-gray-700 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-300 text-sm">
                                <tr>
                                    <th className="px-6 py-3">Database</th>
                                    <th className="px-6 py-3">User</th>
                                    <th className="px-6 py-3">Engine</th>
                                    <th className="px-6 py-3">Tables</th>
                                    <th className="px-6 py-3">Size (MB)</th>
                                    <th className="px-6 py-3">Charset</th>
                                    <th className="px-6 py-3">Collation</th>
                                    <th className="px-6 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="text-sm">
                                {databases.map((db, index) => (
                                    <tr key={`db-${index}`} className="bg-white border-b text-gray-700 dark:text-gray-200 dark:bg-gray-850 dark:border-gray-700 border-gray-200">
                                        <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{db.name}</td>
                                        <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{db.db_user}</td>
                                        <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                {db.engine}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{db.tables}</td>
                                        <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{db.sizeMb}</td>
                                        <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            {db.engine === 'postgres' ? '—' : (db.charset || '—')}
                                        </td>
                                        <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            {db.engine === 'postgres' ? '—' : (db.collation || '—')}
                                        </td>
                                        <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            <div className="flex items-center space-x-2">
                                                <EditDatabaseForm database={db} />
                                                <ConfirmationButton doAction={() => deleteDb(db.id)}>
                                                    <TiDelete className="w-6 h-6 text-red-500" />
                                                </ConfirmationButton>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
            {auth.user.role === 'admin' && <DbServiceControl />}
            <Tooltip id="tooltip-edit" />
        </AuthenticatedLayout>
    );
}
