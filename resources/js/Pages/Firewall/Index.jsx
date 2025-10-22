import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'react-toastify';
import ConfirmationButton from '@/Components/ConfirmationButton';
import { MdSecurity } from 'react-icons/md';
import { FaToggleOn, FaToggleOff } from 'react-icons/fa';
import { TiDelete } from 'react-icons/ti';

export default function FirewallIndex({ status, rules }) {
    const { auth } = usePage().props;
    const [newRule, setNewRule] = useState('');
    const [ruleType, setRuleType] = useState('allow');

    const isEnabled = (status || '').toLowerCase().includes('active') || (status || '').toLowerCase().includes('enabled');

    const toggleFirewall = () => {
        router.post(route('firewall.toggle'), { enabled: !isEnabled }, {
            onBefore: () => toast(`${!isEnabled ? 'Enabling' : 'Disabling'} firewall...`),
            onSuccess: () => router.reload({ only: ['status'] }),
            onError: () => toast('Failed to toggle firewall')
        });
    };

    const addRule = (e) => {
        e.preventDefault();
        if (!newRule.trim()) return;
        router.post(route('firewall.store'), { rule: newRule.trim(), type: ruleType }, {
            onBefore: () => toast('Adding rule...'),
            onSuccess: () => { setNewRule(''); setRuleType('allow'); router.reload({ only: ['rules'] }); },
            onError: () => toast('Failed to add rule')
        });
    };

    const deleteRule = (idOrSpec) => {
        router.delete(route('firewall.destroy', { id: idOrSpec }), {
            onBefore: () => toast('Deleting rule...'),
            onSuccess: () => router.reload({ only: ['rules'] }),
            onError: () => toast('Failed to delete rule')
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col xl:flex-row xl:justify-between max-w-7xl pr-5">
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                        <MdSecurity className='mr-2' />
                        Firewall
                    </h2>
                    <div className="flex items-center space-x-3">
                        <div className={`inline-flex items-center px-3 py-1 rounded-md text-sm font-medium ${isEnabled ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'}`}>
                            {isEnabled ? 'Active' : 'Disabled'}
                        </div>
                        <ConfirmationButton doAction={toggleFirewall}>
                            <button className={`p-2 rounded-lg transition-colors ${isEnabled ? 'bg-green-100 hover:bg-green-200 text-green-600 dark:bg-green-900 dark:hover:bg-green-800 dark:text-green-300' : 'bg-gray-100 hover:bg-gray-200 text-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-400'}`}>
                                {isEnabled ? <FaToggleOn className='w-5 h-5' /> : <FaToggleOff className='w-5 h-5' />}
                            </button>
                        </ConfirmationButton>
                    </div>
                </div>
            }
        >
            <Head title="Firewall" />

            <div className="max-w-7xl px-4 my-8">
                <form onSubmit={addRule} className="bg-white dark:bg-gray-850 p-4 rounded-md flex items-center space-x-3">
                    <input
                        type="text"
                        className="w-full bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                        placeholder="e.g. 22/tcp or proto tcp from 1.2.3.4 to any port 22"
                        value={newRule}
                        onChange={(e) => setNewRule(e.target.value)}
                    />
                    <select
                        className="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                        value={ruleType}
                        onChange={(e) => setRuleType(e.target.value)}
                    >
                        <option value="allow">Allow</option>
                        <option value="deny">Deny</option>
                    </select>
                    <button type="submit" className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">Add Rule</button>
                </form>

                <div className="relative overflow-x-auto bg-white dark:bg-gray-850 mt-3">
                    <table className="w-full text-left rtl:text-right text-gray-500 dark:text-gray-400">
                        <thead className="text-gray-700 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-300 text-sm">
                            <tr>
                                <th className="px-6 py-3">#</th>
                                <th className="px-6 py-3">Service/Port</th>
                                <th className="px-6 py-3">Action</th>
                                <th className="px-6 py-3">Direction</th>
                                <th className="px-6 py-3">From</th>
                                <th className="px-6 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="text-sm">
                            {rules?.map((r, idx) => (
                                <tr key={`rule-${idx}`} className="bg-white border-b text-gray-700 dark:text-gray-200 dark:bg-gray-850 dark:border-gray-700 border-gray-200">
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{r.number}</td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{r.service}</td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{r.action}</td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{r.direction}</td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{r.from}</td>
                                    <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        <div className='flex items-center space-x-2'>
                                            <ConfirmationButton doAction={() => deleteRule(r.number)}>
                                                <TiDelete className='w-6 h-6 text-red-500' />
                                            </ConfirmationButton>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
