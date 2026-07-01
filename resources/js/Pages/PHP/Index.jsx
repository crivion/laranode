import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { TbBrandPhp } from 'react-icons/tb';
import { TiDelete } from 'react-icons/ti';
import { FaToggleOn, FaToggleOff, FaSync } from 'react-icons/fa';
import { toast } from 'react-toastify';
import InstallPHPForm from './Partials/InstallPHPForm';
import ConfirmationButton from '@/Components/ConfirmationButton';
import { useEffect, useState } from 'react';

export default function PHPIndex() {
    const { auth } = usePage().props;
    const [phpVersions, setPhpVersions] = useState([]);
    const [liveStats, setLiveStats] = useState({});
    const [loading, setLoading] = useState(true);

    const echo = window.Echo;

    // Fetch installed PHP versions
    const fetchPhpVersions = () => {
        fetch(route('php.list'), {
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                setPhpVersions(data);
                setLoading(false);
            })
            .catch(() => {
                toast.error('Failed to fetch PHP versions');
                setLoading(false);
            });
    };

    useEffect(() => {
        fetchPhpVersions();

        // Subscribe to live stats
        const dashboardChannel = echo.private("systemstats");

        dashboardChannel.listen("SystemStatsEvent", (data) => {
            if (data.phpFpm) {
                setLiveStats(data.phpFpm);
            }
        });

        const whisperInterval = setInterval(() => {
            dashboardChannel.whisper("typing", { requesting: "dashboard-realtime-stats" });
        }, 2000);

        return () => {
            clearInterval(whisperInterval);
            echo.leave("systemstats");
        };
    }, []);

    const uninstallPhp = (version) => {
        router.delete(route('php.uninstall'), {
            data: { version },
            onBefore: () => toast('Uninstalling PHP ' + version + '...'),
            onSuccess: () => {
                toast.success('PHP ' + version + ' uninstalled successfully');
                fetchPhpVersions();
            },
            onError: () => toast.error('Failed to uninstall PHP ' + version),
        });
    };

    const toggleService = (version, currentEnabled) => {
        const enabled = !currentEnabled;
        const action = enabled ? 'enable' : 'disable';

        router.post(route('php.service.toggle'),
            { version, enabled },
            {
                onBefore: () => toast(`${action === 'enable' ? 'Enabling' : 'Disabling'} PHP ${version}-FPM...`),
                onSuccess: () => {
                    toast.success(`PHP ${version}-FPM ${action}d successfully`);
                    fetchPhpVersions();
                },
                onError: () => toast.error(`Failed to ${action} PHP ${version}-FPM`),
            }
        );
    };

    const restartService = (version) => {
        router.post(route('php.service.restart'),
            { version },
            {
                onBefore: () => toast('Restarting PHP ' + version + '-FPM...'),
                onSuccess: () => {
                    toast.success('PHP ' + version + '-FPM restarted successfully');
                    fetchPhpVersions();
                },
                onError: () => toast.error('Failed to restart PHP ' + version + '-FPM'),
            }
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col xl:flex-row xl:justify-between max-w-7xl pr-5">
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                        <TbBrandPhp className='mr-2' />
                        PHP Versions
                    </h2>
                    <InstallPHPForm installedVersions={phpVersions} />
                </div>
            }
        >
            <Head title="PHP Versions" />

            <div className="max-w-7xl px-4 my-8">
                {loading ? (
                    <div className="text-center py-8 text-gray-600 dark:text-gray-400">
                        Loading PHP versions...
                    </div>
                ) : (
                    <div className="relative overflow-x-auto bg-white dark:bg-gray-850 mt-3">
                        <table className="w-full text-left rtl:text-right text-gray-500 dark:text-gray-400">
                            <thead className="text-gray-700 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-300 text-sm">
                                <tr>
                                    <th className="px-6 py-3">Version</th>
                                    <th className="px-6 py-3">Status</th>
                                    <th className="px-6 py-3">Memory</th>
                                    <th className="px-6 py-3">CPU Time</th>
                                    <th className="px-6 py-3">Uptime</th>
                                    <th className="px-6 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="text-sm">
                                {phpVersions.length === 0 ? (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                            No PHP versions installed
                                        </td>
                                    </tr>
                                ) : (
                                    phpVersions.map((php, index) => {
                                        const stats = liveStats[php.version] || {};
                                        return (
                                            <tr key={`php-${index}`} className="bg-white border-b text-gray-700 dark:text-gray-200 dark:bg-gray-850 dark:border-gray-700 border-gray-200">
                                                <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                    <div className="flex items-center">
                                                        <TbBrandPhp className="w-5 h-5 mr-2 text-blue-600" />
                                                        PHP {php.version}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                    <div className={`inline-flex items-center px-3 py-1 rounded-md text-sm font-medium ${php.status === 'active'
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                            : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'
                                                        }`}>
                                                        {php.status === 'active' ? 'Active' : 'Inactive'}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                    {stats.memory || '--'}
                                                </td>
                                                <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                    {stats.cpuTime || '--'}
                                                </td>
                                                <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                    {stats.uptime || '--'}
                                                </td>
                                                <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                    <div className='flex items-center space-x-2'>
                                                        <ConfirmationButton doAction={() => toggleService(php.version, php.enabled)}>
                                                            <button
                                                                className={`p-2 rounded-lg transition-colors ${php.enabled
                                                                        ? 'bg-green-100 hover:bg-green-200 text-green-600 dark:bg-green-900 dark:hover:bg-green-800 dark:text-green-300'
                                                                        : 'bg-gray-100 hover:bg-gray-200 text-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-400'
                                                                    }`}
                                                                title={php.enabled ? 'Disable Service' : 'Enable Service'}
                                                            >
                                                                {php.enabled ? (
                                                                    <FaToggleOn className='w-5 h-5' />
                                                                ) : (
                                                                    <FaToggleOff className='w-5 h-5' />
                                                                )}
                                                            </button>
                                                        </ConfirmationButton>

                                                        <ConfirmationButton doAction={() => restartService(php.version)}>
                                                            <button
                                                                className="p-2 rounded-lg bg-blue-100 hover:bg-blue-200 text-blue-600 dark:bg-blue-900 dark:hover:bg-blue-800 dark:text-blue-300 transition-colors"
                                                                title="Restart Service"
                                                            >
                                                                <FaSync className='w-4 h-4' />
                                                            </button>
                                                        </ConfirmationButton>

                                                        <ConfirmationButton doAction={() => uninstallPhp(php.version)}>
                                                            <TiDelete className='w-6 h-6 text-red-500' />
                                                        </ConfirmationButton>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
