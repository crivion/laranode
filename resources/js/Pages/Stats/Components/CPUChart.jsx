import React from 'react';
import { Line } from 'react-chartjs-2';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler } from 'chart.js';

// Registering necessary components for the chart
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

const CpuStatsChart = ({ cpuStats }) => {
    // Extract time, user, system, and idle data from the cpuStats
    const times = cpuStats.map(item => item.time);
    const userUsage = cpuStats.map(item => item.user);
    const systemUsage = cpuStats.map(item => item.system);
    const idleUsage = cpuStats.map(item => item.idle);
    const totalUsage = cpuStats.map(item => item.total);

    // Calculate max values for user, system, and total
    const maxTotal = Math.max(...totalUsage);
    const maxUser = Math.max(...userUsage);
    const maxSystem = Math.max(...systemUsage);

    // Define the chart data
    const data = {
        labels: times, // Time labels on X-axis
        datasets: [
            {
                label: 'User',
                data: userUsage,
                borderColor: "rgba(75, 192, 192, 1)",
                backgroundColor: "rgba(75, 192, 192, 0.3)",
                fill: true,
                tension: 0.5,
                pointRadius: 0,
            },
            {
                label: 'System',
                data: systemUsage,
                borderColor: "rgba(255, 99, 132, 1)",
                backgroundColor: "rgba(255, 99, 132, 0.1)",
                fill: true,
                tension: 0.5,
                pointRadius: 0,
            },
            {
                label: 'Idle',
                data: idleUsage,
                borderColor: "rgba(54, 162, 235, 1)",
                backgroundColor: "rgba(54, 162, 235, 0.2)",
                tension: 0.5,
                pointRadius: 0,
                hidden: true
            },
            {
                label: 'Total',
                data: totalUsage,
                borderColor: 'rgba(153, 102, 255, 1)',
                backgroundColor: 'rgba(153, 102, 255, 0.2)',
                fill: true,
                tension: 0.5,
                pointRadius: 0,
            }
        ],
    };

    const options = {
        responsive: true,
        maintainAspectRatio: false,
        backgroundColor: 'white',
        plugins: {
            legend: {
                display: true,
                labels: {
                    usePointStyle: true,
                    pointStyle: 'circle',
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(tooltipItem) {
                        let label = tooltipItem.dataset.label || '';
                        let value = tooltipItem.raw;
                        return `${label}: ${value.toFixed(2)}%`;
                    }
                },
            },
        },
        scales: {
            x: {
                display: false,
                grid: {
                    display: false
                },
                ticks: {
                    autoSkip: true,
                    maxRotation: 45,
                    minRotation: 0
                }
            },
            y: {
                display: false,
                grid: {
                    display: false
                },
                ticks: {
                    beginAtZero: true,
                    stepSize: 10,
                },
            }
        }
    };

    return (<>
        {/* Max CPU usage stats */}
        <div className="mt-4 pb-3 border-gray-200 dark:border-gray-700">
            <div className="text-sm mt-3 flex items-center space-x-2">
                <p>Max Load (User+System):</p>
                <div className='flex-grow'><hr className="border-gray-300 dark:border-gray-700 border-dotted" /></div>
                <span className="font-bold text-indigo-100 bg-indigo-400 px-2 py-0.5 rounded text-xs">{maxTotal.toFixed(2)}%</span>
            </div>
            <div className="text-sm mt-3 flex items-center space-x-2">
                <p>Max User Load:</p>
                <div className='flex-grow'><hr className="border-gray-300 dark:border-gray-700 border-dotted" /></div>
                <span className="font-bold text-emerald-100 bg-emerald-400 px-2 py-0.5 rounded text-xs">{maxUser.toFixed(2)}%</span>
            </div>
            <div className="text-sm mt-3 flex items-center space-x-2">
                <p>Max System Load:</p>
                <div className='flex-grow'><hr className="border-gray-300 dark:border-gray-700 border-dotted" /></div>
                <span className="font-bold text-rose-50 bg-rose-300 px-2 py-0.5 rounded text-xs">{maxSystem.toFixed(2)}%</span>
            </div>
        </div>
        <div className="mt-4 w-full">
            <Line data={data} options={options} />
        </div>
    </>
    );
};

export default CpuStatsChart;

