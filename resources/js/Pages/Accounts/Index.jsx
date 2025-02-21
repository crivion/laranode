import { FaUsers } from "react-icons/fa6";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import CreateUserForm from './Partials/CreateAccountForm';
import ConfirmationButton from "@/Components/ConfirmationButton";
import { TiDelete } from "react-icons/ti";
import { toast } from "react-toastify";
import { router } from '@inertiajs/react'

export default function Accounts({ accounts }) {

    const deleteUser = (id) => {
        router.delete(route('accounts.destroy', { account: id }), {
            onSuccess: page => {
                toast("Account deleted successfully.");
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
                                        {account.name}
                                    </td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {account.email}
                                    </td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {account.limits?.db}/{account.limits?.domains}
                                    </td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {account.role == "admin" ? <span className='bg-green-300 text-green-700 px-2 py-1 text-sm rounded-lg'>Admin</span> : <span className='bg-gray-300 text-gray-700 px-2 py-1 text-sm rounded-lg'>User</span>}
                                    </td>
                                    <td className="px-6 py-4 font-medium text-gray-900
                                    whitespace-nowrap dark:text-white">
                                        Edit / Impersonate

                                        <ConfirmationButton doAction={() => deleteUser(account.id)}>
                                            <TiDelete className='w-6 h-6 text-red-500' />
                                        </ConfirmationButton>

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

