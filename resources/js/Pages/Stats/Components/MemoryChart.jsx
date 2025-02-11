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

const MemoryChart = ({ memoryStats }) => {
    // Extract time, available, used, and percent memory data from memoryStats
    const times = memoryStats.map(item => item.time);
    const availMemory = memoryStats.map(item => item.avail);
    const usedMemory = memoryStats.map(item => item.used);
    const percentMemory = memoryStats.map(item => item.percent);

    // Define the chart data
    const data = {
        labels: times, // Time labels on X-axis
        datasets: [
            {
                label: 'Percent',
                data: percentMemory,
                borderColor: "rgba(75, 192, 192, 1)",
                backgroundColor: "rgba(75, 192, 192, 0.3)",
                fill: true,
                tension: 0.5,
                pointRadius: 0,
            },
            {
                label: 'Used',
                data: usedMemory,
                borderColor: "rgba(255, 99, 132, 1)",
                backgroundColor: "rgba(255, 99, 132, 0.1)",
                fill: true,
                tension: 0.5,
                pointRadius: 0,
            },
            {
                label: 'Available',
                data: availMemory,
                borderColor: "rgba(153, 102, 255, 1)",
                backgroundColor: "rgba(153, 102, 255, 0.2)",
                fill: true,
                tension: 0.5,
                pointRadius: 0,
            },
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

                        if (label === 'Percent') {
                            return `${label}: ${value}%`;
                        }

                        // Convert GB values to a fixed decimal for clarity
                        return `${label}: ${value.toFixed(2)} GB`;
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

    return (
        <>
            {/* Max Memory usage stats */}
            <div className="mt-4 pb-3 border-gray-200 dark:border-gray-700">
                <div className="text-sm mt-3 flex items-center space-x-2">
                    <p>Max Memory Percent:</p>
                    <div className='flex-grow'><hr className="border-gray-300 dark:border-gray-700 border-dotted" /></div>
                    <span className="font-bold text-indigo-100 bg-indigo-400 px-2 py-0.5 rounded text-xs">{Math.max(...percentMemory).toFixed(2)}%</span>
                </div>
                <div className="text-sm mt-3 flex items-center space-x-2">
                    <p>Max Used Memory:</p>
                    <div className='flex-grow'><hr className="border-gray-300 dark:border-gray-700 border-dotted" /></div>
                    <span className="font-bold text-emerald-100 bg-emerald-400 px-2 py-0.5 rounded text-xs">{Math.max(...usedMemory).toFixed(2)} GB</span>
                </div>
                <div className="text-sm mt-3 flex items-center space-x-2">
                    <p>Max Available Memory:</p>
                    <div className='flex-grow'><hr className="border-gray-300 dark:border-gray-700 border-dotted" /></div>
                    <span className="font-bold text-rose-50 bg-rose-300 px-2 py-0.5 rounded text-xs">{Math.max(...availMemory).toFixed(2)} GB</span>
                </div>
            </div>

            {/* Memory chart */}
            <div className="mt-4 w-full">
                <Line data={data} options={options} />
            </div>
        </>
    );
};

export default MemoryChart;

