import { Doughnut } from 'react-chartjs-2';
import { ArcElement, Chart as ChartJS, Legend, Tooltip } from 'chart.js';
import { FaMicrochip, FaMemory } from 'react-icons/fa6';
import { ImSpinner9 } from 'react-icons/im';
import { buildCpuShare, buildMemoryShare } from './processShares';

ChartJS.register(ArcElement, Tooltip, Legend);

function ShareDoughnut({ title, headline, icon, model }) {
    return (
        <div className="rounded-lg bg-white dark:bg-gray-850 shadow p-4">
            <div className="flex items-center justify-between mb-1">
                <div className="flex items-center text-gray-600 dark:text-gray-400 text-sm font-semibold">
                    {icon}
                    <span className="ml-1.5">{title}</span>
                </div>
                {headline && (
                    <span className="text-sm font-bold text-gray-800 dark:text-gray-200">{headline}</span>
                )}
            </div>

            {!model ? (
                <div className="flex justify-center items-center h-40">
                    <ImSpinner9 className="animate-spin w-5 h-5 text-gray-400" />
                </div>
            ) : (
                // maintainAspectRatio:true sizes from width (never a zero-height
                // parent), so the doughnut can't collapse to a dot on mount.
                <div className="relative w-full max-w-xs mx-auto">
                    <Doughnut
                        data={{
                            labels: model.slices.map((s) => s.label),
                            datasets: [
                                {
                                    data: model.slices.map((s) => s.value),
                                    backgroundColor: model.slices.map((s) => s.color),
                                    borderWidth: 1,
                                },
                            ],
                        }}
                        options={{
                            responsive: true,
                            maintainAspectRatio: true,
                            aspectRatio: 1.3,
                            cutout: '60%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { boxWidth: 12, font: { size: 11 }, color: '#6b7280' },
                                },
                                tooltip: {
                                    callbacks: { label: (ctx) => `${ctx.label} — ${ctx.parsed}%` },
                                },
                            },
                        }}
                    />
                </div>
            )}
        </div>
    );
}

/**
 * Two doughnuts above the dashboard stat cards: CPU and RAM usage broken down by
 * process, each with an Idle / Free headroom slice.
 */
const ResourceShareCharts = ({ topStats, cpuStats, memoryStats }) => {
    const cpu = buildCpuShare(topStats, cpuStats);
    const mem = buildMemoryShare(topStats, memoryStats);

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-2">
            <ShareDoughnut
                title="CPU by process"
                headline={cpu ? `${cpu.centerLabel} ${cpu.centerSub}` : null}
                icon={<FaMicrochip className="text-indigo-500 w-4 h-4 flex-shrink-0" />}
                model={cpu}
            />
            <ShareDoughnut
                title="Memory by process"
                headline={mem ? `${mem.centerLabel} ${mem.centerSub}` : null}
                icon={<FaMemory className="text-teal-500 w-4 h-4 flex-shrink-0" />}
                model={mem}
            />
        </div>
    );
};

export default ResourceShareCharts;
