import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { TbDatabase } from 'react-icons/tb';
import { useState } from 'react';
import { toast } from 'react-toastify';

export default function MysqlIndex({ databases = [] }) {

    const { auth } = usePage().props;
    const [renames, setRenames] = useState({});

    const renameDb = (from) => {
        const to = renames[from];
        if (!to) return toast('Enter new database name');
        router.patch(route('mysql.rename'), { from, to }, {
            onBefore: () => toast('Renaming database...'),
            onSuccess: () => toast('Database renamed.'),
            onError: () => toast('Failed to rename database.'),
        });
    };

    const deleteDb = (name) => {
        if (!confirm('Are you sure you want to delete this database?')) return;
        router.delete(route('mysql.destroy'), {
            data: { name },
            onBefore: () => toast('Deleting database...'),
            onSuccess: () => toast('Database deleted.'),
            onError: () => toast('Failed to delete database.'),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col xl:flex-row xl:justify-between max-w-7xl pr-5">
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                        <TbDatabase className='mr-2' />
                        MySQL Databases
                    </h2>
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
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{db.user}</td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{db.tables}</td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{db.sizeMb}</td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{db.charset || '-'}</td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{db.collation || '-'}</td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        <div className='flex items-center space-x-2'>
                                            <input
                                                placeholder="New name"
                                                className="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                                                value={renames[db.name] || ''}
                                                onChange={(e) => setRenames({ ...renames, [db.name]: e.target.value })}
                                            />
                                            <button className="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-2 rounded-lg" onClick={() => renameDb(db.name)}>Rename</button>
                                            <button className="bg-red-600 hover:bg-red-700 text-white text-sm px-3 py-2 rounded-lg" onClick={() => deleteDb(db.name)}>Delete</button>
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


