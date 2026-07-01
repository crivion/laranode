import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { RiDashboard3Fill } from "react-icons/ri";
import { FaGear } from "react-icons/fa6";
import { useEffect, useState } from "react";
import TopProcesses from './Components/TopProcesses';
import ResourceShareCharts from './Components/ResourceShareCharts';
import GpuLive from './Components/GpuLive';
import CPULive from './Components/CPULive';
import MemoryLive from './Components/MemoryLive';
import DiskLive from './Components/DiskLive';
import NetworkLive from './Components/NetworkLive';
import DbEnginesLive from './Components/DbEnginesLive';
import PHPFPMLive from './Components/PHPFPMLive';


export default function Dashboard({ initialStats }) {

    const [liveStats, setLiveStats] = useState(initialStats ?? []);
    const [topStats, setTopStats] = useState([]);
    const [sortBy, setSortBy] = useState("cpu");
    const [topSpinner, setTopSpinner] = useState(false);

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

    // Top-process stats feed the by-process doughnuts AND the processes table.
    // Subscribed once here so the two consumers can't fight over leaving the channel.
    useEffect(() => {
        const topStatsChannel = echo.private("topstats");

        window.axios.get("/dashboard/admin/get/top-sort").then((response) => {
            setSortBy(response.data.sortBy);
        });

        topStatsChannel.listen("TopStatsEvent", (data) => {
            setTopStats(data);
            setTopSpinner(false);
        });

        const whisperInterval = setInterval(() => {
            topStatsChannel.whisper("typing", { requesting: "dashboard-top-stats" });
        }, 2000);

        return () => {
            clearInterval(whisperInterval);
            echo.leave("topstats");
        };
    }, []);

    const changeSort = (next) => {
        window.axios.patch("/dashboard/admin/set/top-sort", { sortBy: next }).then((response) => {
            setSortBy(response.data.sortBy);
            setTopSpinner(true);
        });
    };

    const rescanGpu = () => {
        router.post(route('dashboard.admin.gpuRescan'), {}, { onSuccess: () => router.reload() });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col xl:justify-between xl:flex-row max-w-7xl pr-5">
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                        <RiDashboard3Fill className='mr-2' />
                        Dashboard
                    </h2>
                    <div className="flex items-center gap-3">
                        <button
                            onClick={rescanGpu}
                            title="Rescan for a GPU"
                            className="text-gray-400 hover:text-indigo-500 transition-colors"
                            aria-label="Rescan for a GPU"
                        >
                            <FaGear className="w-5 h-5" />
                        </button>
                        <div className="hidden xl:block">
                            <NetworkLive networkStats={liveStats.network} />
                        </div>
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

                    {/* By-process CPU + RAM doughnuts */}
                    <ResourceShareCharts
                        topStats={topStats}
                        cpuStats={liveStats.cpuStats}
                        memoryStats={liveStats.memoryStats}
                    />

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
                        <DbEnginesLive dbEngines={liveStats.dbEngines} />
                        <PHPFPMLive phpStats={liveStats.phpFpm} />
                    </div>

                    {/* GPU — only rendered when one was detected */}
                    <GpuLive gpu={liveStats.gpu} />

                </div>

                <div className="mx-4 mt-8">
                    <TopProcesses topStats={topStats} sortBy={sortBy} onSort={changeSort} spinner={topSpinner} />
                </div>

            </div>

        </AuthenticatedLayout>
    );
}
