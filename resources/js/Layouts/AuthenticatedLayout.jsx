import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import TopNavi from './Partials/TopNavi';
import SidebarNavi from './Partials/SidebarNavi';
import { ToastContainer, toast } from 'react-toastify';

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const { flash } = usePage().props;
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);

    useEffect(() => {
        if (flash.success) {
            toast(flash.success, { type: 'success' });
        }

        if (flash.error) {
            toast(flash.error, { type: 'error' });
        }
    }, [flash]);


    return (
        <div className="min-h-screen flex flex-col flex-auto flex-shrink-0 antialiase bg-gray-100 dark:bg-gray-900">
            <ToastContainer theme='dark' />
            <TopNavi />
            <SidebarNavi />

            <div className="h-full ml-14 mt-14 mb-10 md:ml-64">
                <main>
                    {header && (
                        <div className="shadow bg-white w-full mx-auto px-4 py-5 dark:bg-gray-900 dark:border-b border-b-gray-800">
                            {header}
                        </div>
                    )}

                    <div className="ml-3 pr-3">
                        {children}
                    </div>
                </main>
            </div>

        </div>
    );
}
