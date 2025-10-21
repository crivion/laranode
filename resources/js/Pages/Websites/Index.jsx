import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import ConfirmationButton from "@/Components/ConfirmationButton";
import { TiDelete } from "react-icons/ti";
import { toast } from "react-toastify";
import { router } from '@inertiajs/react'
import { TbWorldWww } from "react-icons/tb";
import { MdLock, MdLockOpen } from "react-icons/md";
import { FaToggleOn, FaToggleOff } from "react-icons/fa";
import CreateWebsiteForm from "./Partials/CreateWebsiteForm";
import { useEffect, useState } from "react";

export default function Websites({ websites, serverIp }) {

    const { auth } = usePage().props;
    const [phpVersions, setPhpVersions] = useState([]);

    useEffect(() => {
        // fetch available PHP versions once
        fetch(route('php.get-versions'), {
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => setPhpVersions(data))
            .catch(() => setPhpVersions([]));
    }, []);

    const deleteWebsite = (id) => {
        router.delete(route('websites.destroy', { website: id }), {
            onBefore: () => {
                toast("Please wait, deleting website and its resources...");
            },
            onError: errors => {
                toast("Error occured while deleting account.");
                console.log(errors);
            },
        });
    };

    const toggleSsl = (website) => {
        const isEnabled = website.ssl_enabled;
        const action = isEnabled ? 'disable' : 'enable';

        router.post(route('websites.ssl.toggle', { website: website.id }),
            { enabled: !isEnabled },
            {
                onBefore: () => toast(`${action === 'enable' ? 'Enabling' : 'Disabling'} SSL...`),
                onSuccess: () => {
                    toast.success(`SSL ${action === 'enable' ? 'enabled' : 'disabled'} successfully`);
                    router.reload();
                },
                onError: () => toast.error(`Failed to ${action} SSL`),
            }
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col xl:flex-row xl:justify-between max-w-7xl pr-5">
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                        <TbWorldWww className='mr-2' />
                        Websites ({websites.length}/{auth.user.domain_limit || 'unlimited'})
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
                                <th className="px-6 py-3">SSL Status</th>
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
                                        <Link href={`${website.ssl_status === 'active' ? 'https' : 'http'}://${website.url}`} target="_blank" className='hover:underline text-blue-600 dark:text-blue-400'>
                                            <TbWorldWww className='w-4 h-4 inline-flex' /> 
                                            <span className='ml-1'>{website.url}</span>
                                        </Link>
                                    </td>
                                    
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        <div className={`inline-flex items-center px-3 py-1 rounded-md text-sm font-medium ${
                                            website.ssl_status === 'active' 
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                                                : website.ssl_status === 'expired'
                                                ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                : website.ssl_status === 'pending'
                                                ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'
                                        }`}>
                                            {website.ssl_status === 'active' ? (
                                                <MdLock className="w-4 h-4 mr-1" />
                                            ) : (
                                                <MdLockOpen className="w-4 h-4 mr-1" />
                                            )}
                                            {website.ssl_status === 'active' ? 'SSL Active' : 
                                             website.ssl_status === 'expired' ? 'SSL Expired' :
                                             website.ssl_status === 'pending' ? 'SSL Pending' : 'SSL Inactive'}
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        <div className="text-xs inline-flex items-center px-3 rounded-md border border-gray-300 bg-gray-100 text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                            {website.fullDocumentRoot}
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        <select
                                            className="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                                            value={website.php_version?.id || ''}
                                            onChange={(e) => {
                                                const selectedId = e.target.value;
                                                if (!selectedId) return;

                                                router.patch(route('websites.update', { website: website.id }),
                                                    { php_version_id: selectedId },
                                                    {
                                                        onBefore: () => toast('Updating PHP version...'),
                                                        onSuccess: () => toast('PHP version updated.'),
                                                        onError: () => toast('Failed to update PHP version.'),
                                                    }
                                                );
                                            }}
                                        >
                                            <option value="" disabled>Choose version</option>
                                            {phpVersions.map(v => (
                                                <option key={`phpver-${v.id}`} value={v.id}>{v.version}</option>
                                            ))}
                                        </select>
                                    </td>
                                    {auth.user.role == 'admin' && (
                                        <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            <div>
                                                {website.user.username}
                                            </div>
                                            {website.user.role == "admin" ? <span className='bg-green-300 text-green-700 px-2 py-1 text-sm rounded-lg'>Admin</span> : <span className='bg-gray-300 text-gray-700 px-2 py-1 text-sm rounded-lg'>User</span>}
                                        </td>
                                    )}
                                    <td className="px-6 py-4 font-medium text-gray-900
                                    whitespace-nowrap dark:text-white">

                                        <div className='flex items-center space-x-2'>
                                            <ConfirmationButton doAction={() => toggleSsl(website)}>
                                                <button
                                                className={`p-2 rounded-lg transition-colors ${
                                                    website.ssl_enabled 
                                                        ? 'bg-green-100 hover:bg-green-200 text-green-600 dark:bg-green-900 dark:hover:bg-green-800 dark:text-green-300' 
                                                        : 'bg-gray-100 hover:bg-gray-200 text-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-400'
                                                }`}
                                                title={website.ssl_enabled ? 'Disable SSL' : 'Enable SSL'}
                                            >
                                                {website.ssl_enabled ? (
                                                    <FaToggleOn className='w-5 h-5' />
                                                ) : (
                                                    <FaToggleOff className='w-5 h-5' />
                                                )}
                                                </button>
                                            </ConfirmationButton>

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

