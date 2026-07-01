import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import { useForm, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { TbDatabase } from 'react-icons/tb';
import axios from 'axios';

export default function CreateDatabaseForm() {
    const { auth } = usePage().props;
    const [showModal, setShowModal] = useState(false);
    const [engines, setEngines] = useState([]);
    const [selectedEngine, setSelectedEngine] = useState('');
    const [capabilities, setCapabilities] = useState(null);
    const [loading, setLoading] = useState(false);

    const { data, setData, post, processing, reset, clearErrors, errors, transform } = useForm({
        name_suffix: '',
        db_user_suffix: '',
        db_pass: '',
        engine: '',
        charset: '',
        collation: '',
    });

    useEffect(() => {
        if (showModal) {
            fetchEngineList();
        }
    }, [showModal]);

    const fetchEngineList = async () => {
        setLoading(true);
        try {
            const response = await axios.get(route('databases.engine-options'));
            setEngines(response.data.engines || []);
            setCapabilities(response.data.capabilities || null);
        } catch (error) {
            console.error('Error fetching engine options:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleEngineChange = async (engine) => {
        setSelectedEngine(engine);
        setData('engine', engine);

        if (!engine) {
            setCapabilities(null);
            return;
        }

        setLoading(true);
        try {
            const response = await axios.get(
                route('databases.engine-options') + '?engine=' + encodeURIComponent(engine)
            );
            setCapabilities(response.data.capabilities || null);
        } catch (error) {
            console.error('Error fetching engine capabilities:', error);
        } finally {
            setLoading(false);
        }
    };

    const showCreateModal = () => setShowModal(true);

    const closeModal = () => {
        setShowModal(false);
        clearErrors();
        reset();
        setEngines([]);
        setSelectedEngine('');
        setCapabilities(null);
    };

    const createDatabase = (e) => {
        e.preventDefault();
        const prefix = auth.user.username + '_';
        const name = data.name_suffix ? `${prefix}${data.name_suffix}` : '';
        const db_user = data.db_user_suffix ? `${prefix}${data.db_user_suffix}` : '';

        transform((form) => ({
            ...form,
            name,
            db_user,
        }));

        post(route('databases.store'), {
            preserveScroll: true,
            onSuccess: closeModal,
            onFinish: () => transform((form) => form),
        });
    };

    const prefix = auth.user.username + '_';
    const optionFields = capabilities?.optionFields || [];

    return (
        <>
            <button onClick={showCreateModal} className="flex items-center text-gray-700 dark:text-gray-300">
                <TbDatabase className="mr-2" />
                Create Database
            </button>

            <Modal show={showModal} onClose={closeModal}>
                <form onSubmit={createDatabase} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                        <TbDatabase className="mr-2" />
                        Add a New Database
                    </h2>

                    <div className="mt-6 flex flex-col space-y-4 max-h-[500px] overflow-y-auto">
                        {engines.length === 0 && !loading ? (
                            <p className="text-gray-500 dark:text-gray-400">
                                No database engine is currently active.
                            </p>
                        ) : (
                            <>
                                <div>
                                    <InputLabel htmlFor="engine" value="Engine" className="my-2" />
                                    <select
                                        id="engine"
                                        name="engine"
                                        value={selectedEngine}
                                        onChange={(e) => handleEngineChange(e.target.value)}
                                        aria-label="Engine"
                                        className="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md"
                                        disabled={loading}
                                    >
                                        <option value="">Select engine...</option>
                                        {engines.map((eng) => (
                                            <option key={eng} value={eng}>
                                                {eng}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.engine} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="name_suffix" value="Database name" className="my-2" />
                                    <div className="mt-1 flex">
                                        <span className="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 text-sm">
                                            {prefix}
                                        </span>
                                        <div className="flex-1">
                                            <TextInput
                                                id="name_suffix"
                                                name="name_suffix"
                                                value={data.name_suffix}
                                                onChange={(e) => setData('name_suffix', e.target.value)}
                                                className="flex-1 rounded-l-none w-full"
                                                placeholder="mydb"
                                                required
                                            />
                                        </div>
                                    </div>
                                    <InputError message={errors.name} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="db_user_suffix" value="Database user" className="my-2" />
                                    <div className="mt-1 flex">
                                        <span className="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 text-sm">
                                            {prefix}
                                        </span>
                                        <div className="flex-1">
                                            <TextInput
                                                id="db_user_suffix"
                                                name="db_user_suffix"
                                                value={data.db_user_suffix}
                                                onChange={(e) => setData('db_user_suffix', e.target.value)}
                                                className="flex-1 rounded-l-none w-full"
                                                placeholder="user"
                                                required
                                            />
                                        </div>
                                    </div>
                                    <InputError message={errors.db_user} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="db_pass" value="Database password" className="my-2" />
                                    <TextInput
                                        id="db_pass"
                                        name="db_pass"
                                        type="password"
                                        value={data.db_pass}
                                        onChange={(e) => setData('db_pass', e.target.value)}
                                        className="mt-1 block w-full"
                                        required
                                    />
                                    <InputError message={errors.db_pass} className="mt-2" />
                                </div>

                                {optionFields.includes('charset') && (
                                    <div>
                                        <InputLabel htmlFor="charset" value="Charset" className="my-2" />
                                        <TextInput
                                            id="charset"
                                            name="charset"
                                            value={data.charset}
                                            onChange={(e) => setData('charset', e.target.value)}
                                            className="mt-1 block w-full"
                                            placeholder="utf8mb4"
                                        />
                                        <InputError message={errors.charset} className="mt-2" />
                                    </div>
                                )}

                                {optionFields.includes('collation') && (
                                    <div>
                                        <InputLabel htmlFor="collation" value="Collation" className="my-2" />
                                        <TextInput
                                            id="collation"
                                            name="collation"
                                            value={data.collation}
                                            onChange={(e) => setData('collation', e.target.value)}
                                            className="mt-1 block w-full"
                                            placeholder="utf8mb4_unicode_ci"
                                        />
                                        <InputError message={errors.collation} className="mt-2" />
                                    </div>
                                )}

                                {optionFields.includes('encoding') && (
                                    <div>
                                        <InputLabel htmlFor="encoding" value="Encoding" className="my-2" />
                                        <TextInput
                                            id="encoding"
                                            name="encoding"
                                            value={data.encoding || ''}
                                            onChange={(e) => setData('encoding', e.target.value)}
                                            className="mt-1 block w-full"
                                            placeholder="UTF8"
                                        />
                                        <InputError message={errors.encoding} className="mt-2" />
                                    </div>
                                )}

                                {optionFields.includes('locale') && (
                                    <div>
                                        <InputLabel htmlFor="locale" value="Locale" className="my-2" />
                                        <TextInput
                                            id="locale"
                                            name="locale"
                                            value={data.locale || ''}
                                            onChange={(e) => setData('locale', e.target.value)}
                                            className="mt-1 block w-full"
                                            placeholder="en_US.UTF-8"
                                        />
                                        <InputError message={errors.locale} className="mt-2" />
                                    </div>
                                )}

                                <div className="flex justify-end">
                                    <PrimaryButton className="mr-3" disabled={processing || loading}>
                                        Add Database
                                    </PrimaryButton>
                                    <SecondaryButton onClick={closeModal}>Cancel</SecondaryButton>
                                </div>
                            </>
                        )}
                    </div>
                </form>
            </Modal>
        </>
    );
}
