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

const NetworkStatsChart = ({ networkStats }) => {
    // Extracting time, rxkbs, txkbs, and totalkbs data
    const times = networkStats.map(item => item.time);
    const rxkbs = networkStats.map(item => item.rxkbs);
    const txkbs = networkStats.map(item => item.txkbs);
    const totalkbs = networkStats.map(item => item.totalkbs);

    const totalRx = (rxkbs.reduce((a, b) => a + b, 0));
    const totalTx = (txkbs.reduce((a, b) => a + b, 0));
    const totalTraf = (totalkbs.reduce((a, b) => a + b, 0));

    // Define the chart data
    const data = {
        labels: times, // Time labels on X-axis
        datasets: [
            {
                label: 'Received',
                data: rxkbs,
                borderColor: "rgba(75, 192, 192, 1)", // Same color as User in CPU chart
                backgroundColor: "rgba(75, 192, 192, 0.3)", // Same color as User in CPU chart
                fill: true,
                tension: 0.5,
                pointRadius: 0,
            },
            {
                label: 'Transmitted',
                data: txkbs,
                borderColor: "rgba(255, 99, 132, 1)", // Same color as System in CPU chart
                backgroundColor: "rgba(255, 99, 132, 0.1)", // Same color as System in CPU chart
                fill: true,
                tension: 0.5,
                pointRadius: 0,
            },
            {
                label: 'Total',
                data: totalkbs,
                borderColor: "rgba(153, 102, 255, 1)", // Same color as Total in CPU chart
                backgroundColor: "rgba(153, 102, 255, 0.2)", // Same color as Total in CPU chart
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
                        return `${label}: ${value.toFixed(2)} Mb`;
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
                },
            }
        }
    };

    return (
        <>
            {/* Max Network usage stats */}
            <div className="mt-4 pb-3 border-gray-200 dark:border-gray-700">
                <div className="text-sm mt-3 flex items-center space-x-2">
                    <p>Total Rx:</p>
                    <div className='flex-grow'><hr className="border-gray-300 dark:border-gray-700" /></div>
                    <span className="font-bold text-indigo-100 bg-indigo-400 px-2 py-0.5 rounded text-xs">{totalRx.toFixed(2)} Mbps</span>
                </div>
                <div className="text-sm mt-3 flex items-center space-x-2">
                    <p>Total Tx:</p>
                    <div className='flex-grow'><hr className="border-gray-300 dark:border-gray-700" /></div>
                    <span className="font-bold text-emerald-100 bg-emerald-400 px-2 py-0.5 rounded text-xs">{totalTx.toFixed(2)} Mbps</span>
                </div>
                <div className="text-sm mt-3 flex items-center space-x-2">
                    <p>Total (Rx+Tx):</p>
                    <div className='flex-grow'><hr className="border-gray-300 dark:border-gray-700" /></div>
                    <span className="font-bold text-rose-50 bg-rose-300 px-2 py-0.5 rounded text-xs">{totalTraf.toFixed(2)} Mbps</span>
                </div>
            </div>
            <div className="mt-4 w-full">
                <Line data={data} options={options} />
            </div>
        </>
    );
};

export default NetworkStatsChart;

