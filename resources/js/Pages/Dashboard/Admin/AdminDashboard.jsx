import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
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

    const [liveStats, setLiveStats] = useState([]);

    const echo = window.Echo;

    useEffect(() => {

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
    }, []);

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
