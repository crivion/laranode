import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import InputRadio from '@/Components/InputRadio';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm, usePage, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { BsFillInfoCircleFill } from "react-icons/bs";
import { TbWorldWww } from 'react-icons/tb';
import { CopyToClipboard } from 'react-copy-to-clipboard';
import { Transition } from '@headlessui/react';
import Checkbox from '@/Components/Checkbox';
import Dropdown from '@/Components/Dropdown';
import { FaChevronDown } from 'react-icons/fa6';

export default function CreateWebsiteForm({ serverIp }) {
    const { auth } = usePage().props;
    const [showModal, setShowModal] = useState(false);
    const [ipCopied, setIpCopied] = useState(false);
    const [phpVersions, setPhpVersions] = useState([]);

    useEffect(() => {
        getPhpVersions();
    }, []);

    const getPhpVersions = async () => {
        window.axios.get(route('php.get-versions')).then((response) => {
            setPhpVersions(response.data);
        }).catch((error) => {
            toast(error.response.data.message, { type: 'error' });
            console.log(error);
        });
    };

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

                    <div className="mt-6 flex flex-col space-y-4 max-h-[500px]">

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

                        <div>
                            <InputLabel htmlFor="php_version_id" value="PHP Version" className='my-2' />

                            {phpVersions.map((phpVersion) => (
                                <div key={`php-version-${phpVersion.id}`} className="flex items-center">
                                    <InputRadio
                                        id={`php_version_id-${phpVersion.id}`}
                                        name="php_version_id"
                                        value={phpVersion.id}
                                        checked={data.php_version_id === phpVersion.id}
                                        onChange={(e) => setData('php_version_id', Number(e.target.value))}
                                        className="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500"
                                    />
                                    <label htmlFor={`php_version_id-${phpVersion.id}`} className="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">{phpVersion.version}</label>
                                </div>
                            ))}

                            {/*
                            <select
                                id="php_version_id"
                                name="php_version_id"
                                value={data.php_version_id}
                                onChange={(e) => setData('php_version_id', e.target.value)}
                                className="mt-1 block w-full flex-1 border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600 rounded-md"
                            >
                                <option value={""}>-- Select --</option>
                                {phpVersions.map((phpVersion) => (
                                    <option key={`php-version-${phpVersion.id}`} value={phpVersion.id}>{phpVersion.version}</option>
                                ))}
                            </select>
                            */}

                            <InputError
                                message={errors.php_version_id}
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
                </form>
            </Modal>
        </>
    );
}
