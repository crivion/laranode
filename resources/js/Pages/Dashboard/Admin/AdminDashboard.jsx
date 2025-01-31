import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { RiDashboard3Fill } from "react-icons/ri";
import { useEffect, useState } from "react";
import TopProcesses from './Components/TopProcesses';
import CPULive from './Components/CPULive';
import MemoryLive from './Components/MemoryLive';
import DiskLive from './Components/DiskLive';


export default function Dashboard() {

    const [liveStats, setLiveStats] = useState([]);

    const echo = window.Echo;
    const dashboardChannel = echo.private("systemstats");

    useEffect(() => {

        dashboardChannel.listen("SystemStatsEvent", (data) => {
            console.log(data);
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
                <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                    <RiDashboard3Fill className='mr-2' />
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="mt-8 px-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                {/* CPU Usage*/}
                <CPULive cpuStats={liveStats.cpuStats} />

                {/* Memory Usage*/}
                <MemoryLive memoryStats={liveStats.memoryStats} />

                {/* Disk Usage */}
                <DiskLive diskStats={liveStats.diskStats} />

            </div>


            <div className="bg-white dark:bg-gray-850 p-6 mx-4 rounded-lg shadow-md mt-10">
                <TopProcesses />
            </div>

        </AuthenticatedLayout>
    );
}
