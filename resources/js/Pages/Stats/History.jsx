import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { ToastContainer, toast } from 'react-toastify';
import { useEffect, useState } from "react";
import { TbChartHistogram } from "react-icons/tb";
import Dropdown from '@/Components/Dropdown';
import CpuStatsChart from './Components/CPUChart';
import MemoryChart from './Components/MemoryChart';
import MemoryTable from './Components/MemoryTable';
import CPUTable from './Components/CPUTable';
import { FaAnglesDown, FaCheckDouble } from "react-icons/fa6";

export default function StatsHistory({ selectedDate, cpuStats, memoryStats, sarFiles, error }) {

    useEffect(() => {
        if (error?.error) {
            toast(error.error, { type: 'error' });
        }
    }, [error]);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col xl:justify-between xl:flex-row max-w-7xl pr-5 xl:items-center">
                    <h2 className="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                        <TbChartHistogram className='mr-2' />
                        CPU &amp; Memory Stats History
                    </h2>
                    <div>
                        <Dropdown>
                            <Dropdown.Trigger>
                                <div className="flex items-center space-x-2 text-gray-600 dark:text-gray-300 cursor-pointer text-sm">
                                    {selectedDate}
                                    <FaAnglesDown className='ml-1 h-3' />
                                </div>
                            </Dropdown.Trigger>

                            <Dropdown.Content>
                                {Object.entries(sarFiles).sort((a, b) => b[0] - a[0]).map((file, index) => (
                                    <Dropdown.Link key={`sar-f-${index}`} href={`/stats/history?report=${file[1].split('/').pop()}`} className='flex items-center text-xs'>
                                        {file[0] === selectedDate && (
                                            <FaCheckDouble className='mr-2 h-3' />
                                        )}
                                        {file[0]}
                                    </Dropdown.Link>
                                ))}
                            </Dropdown.Content>
                        </Dropdown>
                    </div>
                </div>
            }
        >
            <Head title="CPU & Memory Stats History" />
            <ToastContainer />

            <div className="max-w-7xl">

                <div className="mt-8 px-4"></div>

                <div className="mt-5 px-4 grid grid-cols-1 md:grid-cols-2 gap-6">

                    <div className="shadow-md rounded-lg w-full p-6 bg-white dark:bg-gray-950 text-gray-700 dark:text-gray-300">
                        <h3 className="text-gray-700 dark:text-gray-300 font-semibold">CPU Usage</h3>
                        <CpuStatsChart cpuStats={cpuStats} />
                    </div>

                    <div className="shadow-md rounded-lg w-full p-6 bg-white dark:bg-gray-950 text-gray-700 dark:text-gray-300">
                        <h3 className="text-gray-700 dark:text-gray-300 font-semibold">Memory Usage</h3>
                        <MemoryChart memoryStats={memoryStats} />
                    </div>

                </div>


                <div className="mx-4 mt-5">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div className="shadow-md rounded-lg w-full p-6 bg-white dark:bg-gray-950 text-gray-700 dark:text-gray-300">
                            <p className="flex items-center">
                                <i className="fa-solid fa-microchip text-indigo-500 mr-1.5"></i>
                                CPU History Table
                            </p>
                            <CPUTable cpuStats={cpuStats} />
                        </div>

                        <div className="shadow-md rounded-lg w-full p-6 bg-white dark:bg-gray-950 text-gray-700 dark:text-gray-300">
                            <p className="flex items-center">
                                <i className="fa-solid fa-memory text-teal-500 mr-1.5"></i>
                                Memory History Table
                            </p>
                            <MemoryTable memoryStats={memoryStats} />
                        </div>
                    </div>
                </div>

            </div>

        </AuthenticatedLayout>
    );
}
