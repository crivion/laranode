import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { RiDashboard3Fill } from "react-icons/ri";
import { useEffect, useState } from "react";
import TopProcesses from './Components/TopProcesses';
import CPULive from './Components/CPULive';
import MemoryLive from './Components/MemoryLive';
import DiskLive from './Components/DiskLive';
import NetworkLive from './Components/NetworkLive';
import MySQLLive from './Components/MySQLLive';
import PHPFPMLive from './Components/PHPFPMLive';


export default function Dashboard() {

    const { demo } = usePage().props;
    const demoStats = {
        cpuStats: { usage: 17.4, loadTimes: '0.42 0.38 0.31', processCount: 146, uptime: '12 days, 4 hours' },
        memoryStats: { total: 3840, used: 1624, buffcache: 912, free: 1304 },
        diskStats: { percent: '31%', used: '12.4 GB', free: '27.6 GB', size: '40 GB' },
        network: [{ interface: 'eth0', rx: 8.6, tx: 2.1 }],
        mysql: { memory: '284 MB', cpuTime: '18m 42s', uptime: '12 days' },
        phpFpm: {
            'PHP 8.4': { memory: '126 MB', cpuTime: '6m 18s', uptime: '12 days' },
            'PHP 8.3': { memory: '74 MB', cpuTime: '3m 04s', uptime: '8 days' },
        },
    };
    const [liveStats, setLiveStats] = useState(demo?.enabled ? demoStats : []);

    const echo = window.Echo;

    useEffect(() => {

        if (demo?.enabled || !echo) return;

        const dashboardChannel = echo.private("systemstats");

        dashboardChannel.listen("SystemStatsEvent", (data) => {
            setLiveStats(data);
        });

        const whisperInterval = setInterval(() => {
            dashboardChannel.whisper("typing", { requesting: "dashboard-realtime-stats" });
        }, 2000);

        return () => {
            clearInterval(whisperInterval);
            echo.leave("systemstats");
        };
    }, [demo?.enabled]);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col xl:justify-between xl:flex-row max-w-7xl pr-5">
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                        <RiDashboard3Fill className='mr-2' />
                        Dashboard
                    </h2>
                    <div className="hidden xl:block">
                        <NetworkLive networkStats={liveStats.network} />
                    </div>
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="max-w-7xl">

                <div className="mt-8 px-4">

                    <div className="xl:hidden pb-5">
                        <NetworkLive networkStats={liveStats.network} />
                    </div>

                    {/* CPU Usage*/}
                    <CPULive cpuStats={liveStats.cpuStats} />

                    <div className='flex items-center flex-col xl:flex-row xl:space-x-4'>
                        {/* Memory Usage*/}
                        <div className="mt-5 w-full xl:w-1/2">
                            <MemoryLive memoryStats={liveStats.memoryStats} />
                        </div>


                        {/* Disk Usage */}
                        <div className="mt-5 w-full xl:w-1/2">
                            <DiskLive diskStats={liveStats.diskStats} />
                        </div>
                    </div>

                    <div className="mt-5 w-full grid grid-cols-1 xl:grid-cols-4 gap-4">
                        <MySQLLive mysqlStats={liveStats.mysql} />
                        <PHPFPMLive phpStats={liveStats.phpFpm} />
                    </div>

                </div>

                <div className="mx-4 mt-8">
                    <TopProcesses />
                </div>

            </div>

        </AuthenticatedLayout>
    );
}
