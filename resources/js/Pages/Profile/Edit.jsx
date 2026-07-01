import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import { ImProfile } from "react-icons/im";
import { ToastContainer, toast } from 'react-toastify';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200 flex items-center">
                    <ImProfile className='mr-2' />
                    Profile
                </h2>
            }
        >
            <Head title="Profile" />


            <div className="pb-12 pt-8">
                <div className="max-w-7xl space-y-6 px-4">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-950">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-xl"
                        />
                    </div>

                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-950">
                        <UpdatePasswordForm className="max-w-xl" />
                    </div>

                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-950">
                        <DeleteUserForm className="max-w-xl" />
                    </div>

                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-950">
                        <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">
                            Notification Preferences
                        </h2>
                        <p className="text-sm text-gray-600 dark:text-gray-400 mb-3">
                            Manage which notifications you receive and how they are delivered.
                        </p>
                        <Link
                            href="/profile/notifications"
                            className="inline-block px-4 py-2 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700"
                        >
                            Manage Notifications
                        </Link>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
