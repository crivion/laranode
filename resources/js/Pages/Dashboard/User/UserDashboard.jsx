import { FaUsers } from "react-icons/fa6";
import { TbWorldWww } from 'react-icons/tb';
import { FaDatabase } from 'react-icons/fa';
import { VscFileSubmodule } from 'react-icons/vsc';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';

export default function UserDashboard() {

    const { auth, websitesCount, websitesLimit, databasesCount, databasesLimit } = usePage().props;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col xl:flex-row xl:justify-between max-w-7xl pr-5">
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                        <FaUsers className='mr-2' />
                        Welcome, {auth.user.name}
                    </h2>
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="max-w-7xl px-4 my-8">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="bg-white dark:bg-gray-850 rounded-lg p-5 border border-gray-200 dark:border-gray-800 flex items-center justify-between">
                        <div>
                            <div className="text-sm text-gray-500 dark:text-gray-400">Websites</div>
                            <div className="text-2xl font-semibold text-gray-800 dark:text-gray-100 mt-1">
                                {websitesCount}/{websitesLimit ?? '∞'}
                            </div>
                            <Link href={route('websites.index')} className="inline-block mt-3 text-sm text-blue-600 dark:text-blue-400 hover:underline">Manage Websites</Link>
                        </div>
                        <TbWorldWww className="w-12 h-12 text-indigo-500" />
                    </div>

                    <div className="bg-white dark:bg-gray-850 rounded-lg p-5 border border-gray-200 dark:border-gray-800 flex items-center justify-between">
                        <div>
                            <div className="text-sm text-gray-500 dark:text-gray-400">Databases</div>
                            <div className="text-2xl font-semibold text-gray-800 dark:text-gray-100 mt-1">
                                {databasesCount}/{databasesLimit ?? '∞'}
                            </div>
                            <Link href={route('mysql.index')} className="inline-block mt-3 text-sm text-blue-600 dark:text-blue-400 hover:underline">Manage MySQL DBs</Link>
                        </div>
                        <FaDatabase className="w-12 h-12 text-emerald-500" />
                    </div>

                    <div className="bg-white dark:bg-gray-850 rounded-lg p-5 border border-gray-200 dark:border-gray-800 flex items-center justify-between">
                        <div>
                            <div className="text-sm text-gray-500 dark:text-gray-400">File Manager</div>
                            <div className="text-2xl font-semibold text-gray-800 dark:text-gray-100 mt-1">&nbsp;</div>
                            <Link href={route('filemanager')} className="inline-block mt-3 text-sm text-blue-600 dark:text-blue-400 hover:underline">Open File Manager</Link>
                        </div>
                        <VscFileSubmodule className="w-12 h-12 text-amber-500" />
                    </div>
                </div>

                <div className="mt-8 text-sm text-gray-600 dark:text-gray-400">
                    Logged in as <span className="font-medium">{auth.user.username}</span>
                    {auth.user?.is_impersonating && (
                        <>
                            <span className="mx-2">•</span>
                            <Link href={route('accounts.leaveImpersonation')} className="text-blue-600 dark:text-blue-400 hover:underline">Leave Impersonation</Link>
                        </>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
