import { router } from '@inertiajs/react';
import { useState } from 'react';
import { MdSchedule } from 'react-icons/md';

const PRESETS = [
    { label: 'Every minute', value: '* * * * *' },
    { label: 'Every hour', value: '0 * * * *' },
    { label: 'Daily at 2am', value: '0 2 * * *' },
    { label: 'Weekly (Mon 0:00)', value: '0 0 * * 1' },
    { label: 'Monthly (1st 0:00)', value: '0 0 1 * *' },
    { label: 'Custom…', value: 'custom' },
];

export default function CreateCronJobForm() {
    const [preset, setPreset] = useState('* * * * *');
    const [customSchedule, setCustomSchedule] = useState('');
    const [command, setCommand] = useState('');
    const [label, setLabel] = useState('');
    const [errors, setErrors] = useState({});

    const isCustom = preset === 'custom';
    const schedule = isCustom ? customSchedule : preset;

    const handlePresetChange = (e) => {
        setPreset(e.target.value);
        if (e.target.value !== 'custom') {
            setCustomSchedule('');
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setErrors({});
        router.post(route('cron-jobs.store'), { schedule, command, label }, {
            onError: (e) => setErrors(e),
        });
    };

    return (
        <form
            onSubmit={handleSubmit}
            className="flex flex-wrap gap-3 items-end"
            data-testid="create-cron-job-form"
        >
            <div>
                <label
                    htmlFor="cron-preset"
                    className="block text-sm text-gray-600 dark:text-gray-400 mb-1"
                >
                    Schedule
                </label>
                <select
                    id="cron-preset"
                    value={preset}
                    onChange={handlePresetChange}
                    className="border border-gray-300 rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                    aria-label="Schedule preset"
                >
                    {PRESETS.map((p) => (
                        <option key={p.value} value={p.value}>
                            {p.label}
                        </option>
                    ))}
                </select>
                {errors.schedule && (
                    <p className="text-red-500 text-xs mt-1">{errors.schedule}</p>
                )}
            </div>

            {isCustom && (
                <div>
                    <label
                        htmlFor="cron-custom-schedule"
                        className="block text-sm text-gray-600 dark:text-gray-400 mb-1"
                    >
                        Custom Expression
                    </label>
                    <input
                        id="cron-custom-schedule"
                        type="text"
                        value={customSchedule}
                        onChange={(e) => setCustomSchedule(e.target.value)}
                        placeholder="* * * * *"
                        className="border border-gray-300 rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                        aria-label="Custom cron expression"
                    />
                </div>
            )}

            <div>
                <label
                    htmlFor="cron-command"
                    className="block text-sm text-gray-600 dark:text-gray-400 mb-1"
                >
                    Command
                </label>
                <input
                    id="cron-command"
                    type="text"
                    value={command}
                    onChange={(e) => setCommand(e.target.value)}
                    placeholder="php /home/username_ln/artisan schedule:run"
                    className="border border-gray-300 rounded px-3 py-2 text-sm w-80 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                    aria-label="Cron command"
                />
                {errors.command && (
                    <p className="text-red-500 text-xs mt-1">{errors.command}</p>
                )}
            </div>

            <div>
                <label
                    htmlFor="cron-label"
                    className="block text-sm text-gray-600 dark:text-gray-400 mb-1"
                >
                    Label (optional)
                </label>
                <input
                    id="cron-label"
                    type="text"
                    value={label}
                    onChange={(e) => setLabel(e.target.value)}
                    placeholder="e.g. Daily cleanup"
                    className="border border-gray-300 rounded px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                    aria-label="Cron label"
                />
            </div>

            <button
                type="submit"
                className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2 rounded"
            >
                Add Cron Job
            </button>
        </form>
    );
}
