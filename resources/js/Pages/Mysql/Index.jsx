import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { TbDatabase } from 'react-icons/tb';
import { TiDelete } from 'react-icons/ti';
import { toast } from 'react-toastify';
import CreateDatabaseForm from './Partials/CreateDatabaseForm';
import EditDatabaseForm from './Partials/EditDatabaseForm';
import ConfirmationButton from '@/Components/ConfirmationButton';
import { Tooltip } from 'react-tooltip';

export default function MysqlIndex({ databases = [] }) {

    const { auth } = usePage().props;

    const deleteDb = (id) => {
        router.delete(route('mysql.destroy'), {
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
                        <TbDatabase className='mr-2' />
                        MySQL Databases ({databases.length}/{auth.user.database_limit || 'unlimited'})
                    </h2>
                    <CreateDatabaseForm />
                </div>
            }
        >
            <Head title="MySQL" />

            <div className="max-w-7xl px-4 my-8">
                <div className="relative overflow-x-auto bg-white dark:bg-gray-850 mt-3">
                    <table className="w-full  text-left rtl:text-right text-gray-500 dark:text-gray-400">
                        <thead className="text-gray-700 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-300 text-sm">
                            <tr>
                                <th className="px-6 py-3">Database</th>
                                <th className="px-6 py-3">User</th>
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
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{db.tables}</td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{db.sizeMb}</td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{db.charset || '-'}</td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{db.collation || '-'}</td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        <div className='flex items-center space-x-2'>
                                            <EditDatabaseForm database={db} />
                                            <ConfirmationButton doAction={() => deleteDb(db.id)}>
                                                <TiDelete className='w-6 h-6 text-red-500' />
                                            </ConfirmationButton>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}


