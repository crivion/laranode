import { Doughnut } from 'react-chartjs-2';
import { ArcElement, Chart as ChartJS, Legend, Tooltip } from 'chart.js';
import { BsGpuCard } from 'react-icons/bs';
import { FaTemperatureHalf, FaBolt } from 'react-icons/fa6';
import { GiProgression } from 'react-icons/gi';
import { FaMemory } from 'react-icons/fa6';

ChartJS.register(ArcElement, Tooltip, Legend);

const USED_COLOR = '#6366f1';
const HEADROOM_COLOR = '#1f9d55'; // green idle/free

function Gauge({ usedLabel, usedValue, restLabel, restValue, unit = '%' }) {
    return (
        // aspectRatio sizing avoids the zero-height-mount doughnut collapse.
        <div className="relative w-full max-w-xs mx-auto">
            <Doughnut
                data={{
                    labels: [usedLabel, restLabel],
                    datasets: [
                        {
                            data: [usedValue, restValue],
                            backgroundColor: [USED_COLOR, HEADROOM_COLOR],
                            borderWidth: 1,
                        },
                    ],
                }}
                options={{
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 1.4,
                    cutout: '62%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 }, color: '#6b7280' } },
                        tooltip: { callbacks: { label: (ctx) => `${ctx.label} — ${ctx.parsed}${unit}` } },
                    },
                }}
            />
        </div>
    );
}

function StatCard({ icon, label, value }) {
    return (
        <div className="font-bold text-gray-900 dark:text-gray-300 flex flex-col py-3 px-6 rounded-lg bg-white dark:bg-gray-850 shadow">
            <div className="flex items-center justify-center text-sm">
                {icon}
                <span className="ml-1">{label}</span>
            </div>
            <div className="text-center text-lg mt-1.5">{value}</div>
        </div>
    );
}

/**
 * GPU section — only rendered when a GPU was detected (liveStats.gpu is null
 * otherwise). Two gauges (utilisation, VRAM) plus stat cards.
 */
const GpuLive = ({ gpu }) => {
    if (!gpu) return null;

    const util = Number(gpu.util) || 0;
    const idle = Math.max(0, 100 - util);
    const vramUsed = Number(gpu.vramUsed) || 0;
    const vramTotal = Number(gpu.vramTotal) || 0;
    const vramFree = Math.max(0, vramTotal - vramUsed);

    return (
        <div className="mt-5">
            <div className="flex items-center space-x-2">
                <BsGpuCard className="text-purple-500 w-5 h-5 flex-shrink-0" />
                <div className="text-gray-600 dark:text-gray-400 text-lg">GPU</div>
                <div className="bg-gray-200 dark:bg-gray-700 rounded-full text-xs font-semibold text-gray-800 dark:text-gray-200 py-1 px-2 uppercase">
                    {gpu.name} {gpu.vendor ? `(${gpu.vendor})` : ''}
                </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                <div className="rounded-lg bg-white dark:bg-gray-850 shadow p-4">
                    <div className="flex items-center justify-between mb-1 text-sm font-semibold text-gray-600 dark:text-gray-400">
                        <span>Utilisation</span>
                        <span className="text-gray-800 dark:text-gray-200">{util}%</span>
                    </div>
                    <Gauge usedLabel="Used" usedValue={util} restLabel="Idle" restValue={idle} />
                </div>

                <div className="rounded-lg bg-white dark:bg-gray-850 shadow p-4">
                    <div className="flex items-center justify-between mb-1 text-sm font-semibold text-gray-600 dark:text-gray-400">
                        <span>VRAM</span>
                        <span className="text-gray-800 dark:text-gray-200">{vramUsed} / {vramTotal} GB</span>
                    </div>
                    <Gauge usedLabel="Used" usedValue={vramUsed} restLabel="Free" restValue={vramFree} unit=" GB" />
                </div>
            </div>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mt-3">
                <StatCard icon={<GiProgression className="text-indigo-500 w-5 h-5" />} label="Utilisation" value={`${util}%`} />
                <StatCard icon={<FaMemory className="text-teal-500 w-5 h-5" />} label="VRAM" value={`${vramUsed} / ${vramTotal} GB`} />
                <StatCard icon={<FaTemperatureHalf className="text-orange-400 w-5 h-5" />} label="Temp" value={`${gpu.temp ?? '--'}°C`} />
                <StatCard icon={<FaBolt className="text-yellow-400 w-5 h-5" />} label="Power" value={`${gpu.power ?? '--'} W`} />
            </div>
        </div>
    );
};

export default GpuLive;
