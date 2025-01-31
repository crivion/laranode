import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { RiDashboard3Fill } from "react-icons/ri";
import { useEffect, useState } from "react";
import ShowError from '@/Components/ShowError';
import StatCard from '@/Components/StatCard';
import { FaMicrochip, FaMemory, FaHardDrive, FaBarsProgress, FaArrowUpShortWide, FaSitemap, FaUsers, FaLock } from "react-icons/fa6";
import { SiApache, SiNginx, SiMysql, SiPhp } from "react-icons/si";



export default function Dashboard() {

    const [liveStats, setLiveStats] = useState([]);
    const [topStats, setTopStats] = useState([]);

    const echo = window.Echo;
    const dashboardChannel = echo.private("systemstats");
    const topStatsChannel = echo.private("topstats");


    useEffect(() => {

        dashboardChannel.listen("SystemStatsEvent", (data) => {
            console.log(data);
            console.log(typeof data.phpStatus);
            setLiveStats(data);
        });

        topStatsChannel.listen("TopStatsEvent", (data) => {
            // console.log(data);
            setTopStats(data);
        });

        // Set interval to "whisper" every 2 seconds
        // Makes it so we get stats via sockets
        const whisperInterval = setInterval(() => {
            dashboardChannel.whisper("typing", { requesting: "dashboard-realtime-stats" });
            topStatsChannel.whisper("typing", { requesting: "dashboard-top-stats" });
        }, 2000);

        return () => {
            clearInterval(whisperInterval);
            echo.leave("systemstats");
            echo.leave("topstats");
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
                <StatCard icon={<FaMicrochip className="text-indigo-500 text-3xl" />} label="CPU Usage" value={`${liveStats?.cpuUsage}%`} />
                <StatCard icon={<FaMemory className="text-teal-500 text-3xl" />} label="Memory Usage" value={`${liveStats?.memoryUsage} MB`} />
                <StatCard icon={<FaHardDrive className="text-purple-500 text-3xl" />} label="Disk Usage" value={liveStats?.diskUsage} />
                <StatCard icon={<FaBarsProgress className="text-orange-300 text-3xl" />} label="Load Times" value={liveStats?.loadTimes} />
                <StatCard icon={<FaArrowUpShortWide className="text-lime-200 text-3xl" />} label="Uptime" value={liveStats?.uptime} />
                <StatCard icon={<FaSitemap className="text-pink-400 text-3xl" />} label="Processes" value={liveStats?.processCount} />
                <StatCard icon={<FaUsers className="text-blue-400 text-3xl" />} label="Accounts" value={`${liveStats?.userCount} users, ${liveStats?.domainCount} domains`} />
                <StatCard icon={<SiApache className="text-fuchsia-600 w-10 h-10" />} label="Apache Server" value={liveStats?.apache?.memory} status={liveStats?.apache?.status} />
                <StatCard icon={<SiNginx className="text-lime-500 w-10 h-10" />} label="Nginx Status" value={liveStats?.nginxStatus} subtext={`Port: ${liveStats?.nginxPort || 'N/A'}`} />
                <StatCard icon={<SiMysql className="text-sky-500 w-10 h-10" />} label="MySQL Server" value={liveStats?.mysql?.memory} status={liveStats?.mysql?.status} />
                <StatCard icon={<FaLock className="text-green-500 text-3xl" />} label="SSL Status" value={liveStats?.sslStatus} />
            </div>


            <div className="bg-white dark:bg-gray-850 p-6 mx-4 rounded-lg shadow-md mt-10">
                <div className="flex items-center justify-between flex-wrap">
                    <h3 className="text-lg font-semibold text-gray-600 dark:text-gray-400">Top 20 Processes</h3>
                    <div>
                        TOGGLE CPu | MEm
                    </div>
                </div>

                <div className="relative overflow-x-auto pt-8 pb-12">

                    {topStats?.error && <ShowError error={topStats?.error} />}

                    <table className="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                        <thead className="text-gray-700 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-300">
                            <tr>
                                <th className="px-6 py-3">PID</th>
                                <th className="px-6 py-3">%CPU</th>
                                <th className="px-6 py-3">%MEM</th>
                                <th className="px-6 py-3">USER</th>
                                <th className="px-6 py-3">COMMAND</th>
                            </tr>
                        </thead>
                        <tbody className="text-sm">
                            {topStats?.processes?.length > 0 ? (
                                topStats?.processes?.map((process, index) => (
                                    <tr key={`proc-${index}`} className="bg-white border-b text-gray-700 dark:text-gray-200 dark:bg-gray-850 dark:border-gray-700 border-gray-200">
                                        <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            {process.pid}
                                        </td>
                                        <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            {process.cpu}%
                                        </td>
                                        <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            {process.mem}%
                                        </td>
                                        <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            {process.user}
                                        </td>
                                        <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            <button
                                                type="button"
                                                title={process.restOfCmd?.join(" ") || ""} // Tooltip equivalent
                                            >
                                                {process.mainCmd}
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan="5" className="px-6 py-4 text-center text-gray-500">
                                        No processes found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
