import { FaUsers } from "react-icons/fa6";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import ConfirmationButton from "@/Components/ConfirmationButton";
import { TiDelete } from "react-icons/ti";
import { toast } from "react-toastify";
import { router } from '@inertiajs/react'
import { TbWorldWww } from "react-icons/tb";
import { FaDatabase, FaEdit } from "react-icons/fa";
import { Tooltip } from 'react-tooltip'
import CreateWebsiteForm from "./Partials/CreateWebsiteForm";

export default function Websites({ websites, serverIp }) {

    const { auth } = usePage().props;

    const deleteWebsite = (id) => {
        router.delete(route('accounts.destroy', { account: id }), {
            onBefore: () => {
                toast("Please wait, deleting account and its resources...");
            },
            onError: errors => {
                toast("Error occured while deleting account.");
                console.log(errors);
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col xl:flex-row xl:justify-between max-w-7xl pr-5">
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                        <TbWorldWww className='mr-2' />
                        Websites
                    </h2>
                    <CreateWebsiteForm serverIp={serverIp} className="max-w-xl" />
                </div>
            }
        >
            <Head title="Websites" />

            <div className="max-w-7xl px-4 my-8">

                <div className="relative overflow-x-auto bg-white dark:bg-gray-850 mt-3">
                    <table className="w-full  text-left rtl:text-right text-gray-500 dark:text-gray-400">
                        <thead className="text-gray-700 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-300 text-sm">
                            <tr>
                                <th className="px-6 py-3">URL</th>
                                <th className="px-6 py-3">Document Root</th>
                                <th className="px-6 py-3">PHP Version</th>
                                {auth.user.role == 'admin' && (
                                    <th className="px-6 py-3">User</th>
                                )}
                                <th className="px-6 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="text-sm">
                            {websites?.map((website, index) => (
                                <tr key={`website-${index}`} className="bg-white border-b text-gray-700 dark:text-gray-200 dark:bg-gray-850 dark:border-gray-700 border-gray-200">
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {website.url}
                                    </td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {website.document_root}
                                    </td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {website.php_version.version}
                                    </td>
                                    {auth.user.role == 'admin' && (
                                        <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            {website.user.username}
                                            <div>
                                                {website.user.username}
                                            </div>
                                            {website.user.role == "admin" ? <span className='bg-green-300 text-green-700 px-2 py-1 text-sm rounded-lg'>Admin</span> : <span className='bg-gray-300 text-gray-700 px-2 py-1 text-sm rounded-lg'>User</span>}
                                        </td>
                                    )}
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {website.user.role == "admin" ? <span className='bg-green-300 text-green-700 px-2 py-1 text-sm rounded-lg'>Admin</span> : <span className='bg-gray-300 text-gray-700 px-2 py-1 text-sm rounded-lg'>User</span>}
                                    </td>
                                    <td className="px-6 py-4 font-medium text-gray-900
                                    whitespace-nowrap dark:text-white">

                                        <div className='flex items-center space-x-2'>
                                            {/* <EditAccountForm account={account} /> */}

                                            <ConfirmationButton doAction={() => deleteWebsite(website.id)}>
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

