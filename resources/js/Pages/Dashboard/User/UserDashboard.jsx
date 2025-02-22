import { FaUsers } from "react-icons/fa6";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';

export default function UserDashboard() {

    const { auth } = usePage().props;

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
            <Head title="Accounts" />

            <div className="max-w-7xl px-4 my-8">
                This is the user dashboard, {auth.user.username}
                <br />
                <Link href={route('accounts.leaveImpersonation')}>
                    Leave Impersonation
                </Link>
            </div>
        </AuthenticatedLayout>
    );
}
