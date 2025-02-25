import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { BsFillInfoCircleFill } from "react-icons/bs";
import { TbWorldWww } from 'react-icons/tb';
import { CopyToClipboard } from 'react-copy-to-clipboard';
import { Transition } from '@headlessui/react';

export default function CreateWebsiteForm({ serverIp }) {
    const { auth } = usePage().props;
    const [showModal, setShowModal] = useState(false);
    const [ipCopied, setIpCopied] = useState(false);

    const {
        data,
        setData,
        post,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        url: '',
        document_root: '/',
        php_version_id: null,
    });

    const showCreateModal = () => {
        setShowModal(true);
    };

    const createWebsite = (e) => {
        e.preventDefault();

        post(route('websites.store'), {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
            },
        });
    };

    const closeModal = () => {
        setShowModal(false);

        clearErrors();
        reset();
    };

    return (
        <>
            <button onClick={showCreateModal} className='flex items-center text-gray-700 dark:text-gray-300'>
                <TbWorldWww className='mr-2' />
                Create Website
            </button>

            <Modal show={showModal} onClose={closeModal}>
                <form onSubmit={createWebsite} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                        <TbWorldWww className='mr-2' />
                        Add a New Website
                    </h2>

                    <div className="mt-6 flex flex-col space-y-4 max-h-[500px] overflow-scroll">

                        <div class="bg-gray-200 dark:bg-gray-700 p-4 rounded-md text-gray-700 dark:text-gray-300 flex items-center text-xs">
                            <div>
                                <BsFillInfoCircleFill className='mr-2 h-6 w-6' />
                            </div>
                            <div>
                                IMPORTANT: You must point your domain A record via DNS to this server IP:
                                <br />
                                <CopyToClipboard onCopy={() => setIpCopied(true)} text={serverIp}>
                                    <span className="cursor-pointer">
                                        {serverIp}
                                    </span>
                                </CopyToClipboard>

                                <Transition
                                    show={ipCopied}
                                    enter="transition ease-in-out"
                                    enterFrom="opacity-0"
                                    leave="transition ease-in-out"
                                    leaveTo="opacity-0"
                                >
                                    <p className="text-gray-600 dark:text-gray-400 text-xs">
                                        IP copied to clipboard.
                                    </p>
                                </Transition>
                            </div>
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="url"
                                value="URL *no protocol [http|https] - just the domain"
                                className='my-2'
                            />

                            <TextInput
                                id="url"
                                name="url"
                                value={data.url}
                                onChange={(e) =>
                                    setData('url', e.target.value)
                                }
                                className="mt-1 block w-full"
                                isFocused
                                placeholder="example.org"
                                required
                            />

                            <InputError
                                message={errors.url}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel htmlFor="document_root" className='my-2'>
                                <div className="block text-sm font-medium text-gray-700 dark:text-gray-300 my-2">
                                    Document Root
                                </div>
                            </InputLabel>

                            <TextInput
                                id="document_root"
                                name="document_root"
                                value={data.document_root}
                                onChange={(e) => setData('document_root', e.target.value)}
                                className="mt-1 block w-full"
                                placeholder="Document Root"
                                required
                            />

                            <div className="text-xs inline-flex items-center px-3 rounded-md border border-gray-300 bg-gray-100 text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                {auth.user.homedir}/domains/{data.url}{data.document_root}
                            </div>

                            <InputError
                                message={errors.document_root}
                                className="mt-2"
                            />
                        </div>

                        <div className="flex justify-end">
                            <PrimaryButton className="mr-3" disabled={processing}>
                                Add Website
                            </PrimaryButton>

                            <SecondaryButton onClick={closeModal}>
                                Cancel
                            </SecondaryButton>
                        </div>
                    </div>
                </form >
            </Modal >
        </>
    );
}
