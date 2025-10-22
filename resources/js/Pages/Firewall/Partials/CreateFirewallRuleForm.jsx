import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { MdSecurity } from 'react-icons/md';

export default function CreateFirewallRuleForm() {
    const [showModal, setShowModal] = useState(false);

    const {
        data,
        setData,
        post,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        type: 'allow',
        protocol: 'tcp',
        port: '',
        ip: 'any',
        to: 'any',
        comment: '',
    });

    const openModal = () => setShowModal(true);

    const closeModal = () => {
        setShowModal(false);
        clearErrors();
        reset({ type: 'allow', protocol: 'tcp', port: '', ip: 'any', to: 'any', comment: '' });
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('firewall.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    };

    return (
        <>
            <button onClick={openModal} className='flex items-center text-gray-700 dark:text-gray-300'>
                <MdSecurity className='mr-2' />
                Add Firewall Rule
            </button>

            <Modal show={showModal} onClose={closeModal}>
                <form onSubmit={submit} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                        <MdSecurity className='mr-2' />
                        Add Firewall Rule
                    </h2>

                    <div className="mt-6 flex flex-col space-y-4 max-h-[500px]">
                        <div>
                            <InputLabel htmlFor="type" value="Action" className='my-2' />
                            <select
                                id="type"
                                name="type"
                                value={data.type}
                                onChange={(e) => setData('type', e.target.value)}
                                className="mt-1 block w-full flex-1 bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                            >
                                <option value="allow">Allow</option>
                                <option value="deny">Deny</option>
                            </select>
                            <InputError message={errors.type} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="protocol" value="Protocol" className='my-2' />
                            <select
                                id="protocol"
                                name="protocol"
                                value={data.protocol}
                                onChange={(e) => setData('protocol', e.target.value)}
                                className="mt-1 block w-full flex-1 bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                            >
                                <option value="tcp">TCP</option>
                                <option value="udp">UDP</option>
                            </select>
                            <InputError message={errors.protocol} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="port" value="Port" className='my-2' />
                            <TextInput
                                id="port"
                                name="port"
                                type="number"
                                min="1"
                                max="65535"
                                value={data.port}
                                onChange={(e) => setData('port', e.target.value)}
                                className="mt-1 block w-full"
                                placeholder="22"
                                required
                            />
                            <InputError message={errors.port} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="ip" value="IP" className='my-2' />
                            <TextInput
                                id="ip"
                                name="ip"
                                value={data.ip}
                                onChange={(e) => setData('ip', e.target.value)}
                                className="mt-1 block w-full"
                                placeholder="any or 1.2.3.4 or 1.2.3.0/24"
                            />
                            <InputError message={errors.ip} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="to" value="To" className='my-2' />
                            <TextInput
                                id="to"
                                name="to"
                                value={data.to}
                                onChange={(e) => setData('to', e.target.value)}
                                className="mt-1 block w-full"
                                placeholder="any"
                            />
                            <InputError message={errors.to} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="comment" value="Comment" className='my-2' />
                            <TextInput
                                id="comment"
                                name="comment"
                                value={data.comment}
                                onChange={(e) => setData('comment', e.target.value)}
                                className="mt-1 block w-full"
                                placeholder="Optional"
                            />
                            <InputError message={errors.comment} className="mt-2" />
                        </div>

                        <div className="flex justify-end">
                            <PrimaryButton className="mr-3" disabled={processing}>
                                Add Rule
                            </PrimaryButton>
                            <SecondaryButton onClick={closeModal}>
                                Cancel
                            </SecondaryButton>
                        </div>
                    </div>
                </form>
            </Modal>
        </>
    );
}
