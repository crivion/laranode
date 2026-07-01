import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { MdSchedule } from 'react-icons/md';
import { TiDelete } from 'react-icons/ti';
import { toast } from 'react-toastify';
import CreateCronJobForm from './Partials/CreateCronJobForm';
import ConfirmationButton from '@/Components/ConfirmationButton';

export default function Index({ cronJobs = [] }) {
    const toggleActive = (cronJob) => {
        router.post(
            route('cron-jobs.toggle', { cronJob: cronJob.id }),
            {},
            {
                onBefore: () => toast(`${cronJob.active ? 'Pausing' : 'Activating'} cron job…`),
                onError: () => toast.error('Failed to toggle cron job.'),
            }
        );
    };

    const deleteCronJob = (cronJob) => {
        router.delete(
            route('cron-jobs.destroy', { cronJob: cronJob.id }),
            {
                onBefore: () => toast('Deleting cron job…'),
                onError: () => toast.error('Failed to delete cron job.'),
            }
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col xl:flex-row xl:justify-between max-w-7xl pr-5">
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                        <MdSchedule className="mr-2" />
                        Cron Jobs ({cronJobs.length})
                    </h2>
                </div>
            }
        >
            <Head title="Cron Jobs" />

            <div className="max-w-7xl px-4 my-8 space-y-6">
                <section>
                    <h3 className="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-3">
                        Add Cron Job
                    </h3>
                    <CreateCronJobForm />
                </section>

                <section>
                    <h3 className="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-3">
                        Scheduled Jobs
                    </h3>
                    {cronJobs.length === 0 ? (
                        <p className="text-gray-500 dark:text-gray-400">No cron jobs configured.</p>
                    ) : (
                        <div className="relative overflow-x-auto bg-white dark:bg-gray-850">
                            <table className="w-full text-left text-gray-500 dark:text-gray-400 text-sm">
                                <thead className="text-gray-700 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-300">
                                    <tr>
                                        <th className="px-6 py-3">Schedule</th>
                                        <th className="px-6 py-3">Command</th>
                                        <th className="px-6 py-3">Label</th>
                                        <th className="px-6 py-3">Status</th>
                                        <th className="px-6 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {cronJobs.map((job) => (
                                        <tr
                                            key={`cron-${job.id}`}
                                            className="bg-white border-b text-gray-700 dark:text-gray-200 dark:bg-gray-850 dark:border-gray-700 border-gray-200"
                                        >
                                            <td className="px-6 py-4 font-mono whitespace-nowrap">
                                                {job.schedule}
                                            </td>
                                            <td className="px-6 py-4 font-mono text-xs max-w-xs truncate">
                                                {job.command}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                {job.label ?? '—'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <button
                                                    onClick={() => toggleActive(job)}
                                                    className={`px-3 py-1 rounded text-xs font-medium transition-colors ${
                                                        job.active
                                                            ? 'bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900 dark:text-green-200 dark:hover:bg-green-800'
                                                            : 'bg-gray-200 text-gray-600 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600'
                                                    }`}
                                                    aria-label={job.active ? 'Pause cron job' : 'Activate cron job'}
                                                >
                                                    {job.active ? 'Active' : 'Paused'}
                                                </button>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <ConfirmationButton doAction={() => deleteCronJob(job)}>
                                                    <TiDelete className="w-6 h-6 text-red-500" />
                                                </ConfirmationButton>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
