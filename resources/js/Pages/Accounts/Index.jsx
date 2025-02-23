import { FaUsers } from "react-icons/fa6";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import CreateUserForm from './Partials/CreateAccountForm';
import ConfirmationButton from "@/Components/ConfirmationButton";
import { TiDelete } from "react-icons/ti";
import { toast } from "react-toastify";
import { router } from '@inertiajs/react'
import { TbWorldWww } from "react-icons/tb";
import { RiLoginCircleLine } from "react-icons/ri";
import { FaDatabase, FaEdit } from "react-icons/fa";

export default function Accounts({ accounts }) {


    const deleteUser = (id) => {
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
                        <FaUsers className='mr-2' />
                        Accounts
                    </h2>
                    <CreateUserForm className="max-w-xl" />
                </div>
            }
        >
            <Head title="Accounts" />

            <div className="max-w-7xl px-4 my-8">

                <div className="relative overflow-x-auto bg-white dark:bg-gray-850 mt-3">
                    <table className="w-full  text-left rtl:text-right text-gray-500 dark:text-gray-400">
                        <thead className="text-gray-700 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-300 text-sm">
                            <tr>
                                <th className="px-6 py-3">ID</th>
                                <th className="px-6 py-3">Name</th>
                                <th className="px-6 py-3">Email</th>
                                <th className="px-6 py-3">Limits</th>
                                <th className="px-6 py-3">SSH/SFTP</th>
                                <th className="px-6 py-3">Role</th>
                                <th className="px-6 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="text-sm">
                            {accounts?.map((account, index) => (
                                <tr key={`acc-${index}`} className="bg-white border-b text-gray-700 dark:text-gray-200 dark:bg-gray-850 dark:border-gray-700 border-gray-200">
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {account.id}
                                    </td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        <div>
                                            {account.name}
                                        </div>
                                        <span className='text-xs bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 px-2 py-1 rounded-lg'>
                                            {account.username}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {account.email}
                                    </td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        <div className='flex items-center'>
                                            <FaDatabase className='w-4 h-4 mr-1' />
                                            <span className="text-sm bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 p-1 rounded-full">
                                                {account.domain_limit ?? '∞'}
                                            </span>
                                        </div>
                                        <div className='flex items-center mt-1.5'>
                                            <TbWorldWww className='w-4 h-4 mr-1' />
                                            <span className="text-sm bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 p-1 rounded-full">
                                                {account.database_limit ?? '∞'}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {!account.ssh_access ? <span className='bg-amber-200 text-amber-700 px-2 py-1 rounded-lg'>No</span> : <span className='bg-lime-300 text-lime-700 px-2 py-0.5 text-sm rounded-lg'>Yes</span>}
                                    </td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {account.role == "admin" ? <span className='bg-green-300 text-green-700 px-2 py-1 text-sm rounded-lg'>Admin</span> : <span className='bg-gray-300 text-gray-700 px-2 py-1 text-sm rounded-lg'>User</span>}
                                    </td>
                                    <td className="px-6 py-4 font-medium text-gray-900
                                    whitespace-nowrap dark:text-white">

                                        <div className='flex items-center space-x-2'>
                                            <FaEdit className='w-4 h-4' />

                                            <Link href={route('accounts.impersonate', { user: account.id })}>
                                                <RiLoginCircleLine className='w-4 h-4' />
                                            </Link>

                                            <ConfirmationButton doAction={() => deleteUser(account.id)}>
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

