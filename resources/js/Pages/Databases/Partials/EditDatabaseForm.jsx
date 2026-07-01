import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import { useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { TbDatabase } from 'react-icons/tb';
import { FaEdit } from 'react-icons/fa';
import axios from 'axios';

export default function EditDatabaseForm({ database }) {
    const [showModal, setShowModal] = useState(false);
    const [capabilities, setCapabilities] = useState(null);
    const [loading, setLoading] = useState(false);

    const { data, setData, patch, processing, reset, clearErrors, errors } = useForm({
        id: database.id || 0,
        charset: database.charset || '',
        collation: database.collation || '',
        db_password: '',
        encoding: database.encoding || '',
        locale: database.locale || '',
    });

    useEffect(() => {
        if (showModal && database.engine) {
            fetchCapabilities(database.engine);
        }
    }, [showModal]);

    const fetchCapabilities = async (engine) => {
        setLoading(true);
        try {
            const url = route('databases.engine-options') + '?engine=' + encodeURIComponent(engine);
            const response = await axios.get(url);
            setCapabilities(response.data.capabilities || null);
        } catch (error) {
            console.error('Error fetching engine capabilities:', error);
        } finally {
            setLoading(false);
        }
    };

    const showEditModal = () => {
        setShowModal(true);
        setData({
            id: database.id,
            charset: database.charset || '',
            collation: database.collation || '',
            db_password: '',
            encoding: database.encoding || '',
            locale: database.locale || '',
        });
    };

    const closeModal = () => {
        setShowModal(false);
        clearErrors();
        reset();
        setCapabilities(null);
    };

    const updateDatabase = (e) => {
        e.preventDefault();
        patch(route('databases.update'), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    };

    const optionFields = capabilities?.optionFields || [];

    return (
        <>
            <button
                onClick={showEditModal}
                className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                data-tooltip-id={`tooltip-edit-${database.name}`}
                data-tooltip-content="Edit Database"
                data-tooltip-place="top"
            >
                <FaEdit className="w-4 h-4" />
            </button>

            <Modal show={showModal} onClose={closeModal}>
                <form onSubmit={updateDatabase} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                        <TbDatabase className="mr-2" />
                        Edit Database: {database.name}
                    </h2>

                    <div className="mt-6 flex flex-col space-y-4 max-h-[500px] overflow-y-auto">
                        <div>
                            <InputLabel htmlFor="engine_readonly" value="Engine" className="my-2" />
                            <TextInput
                                id="engine_readonly"
                                name="engine_readonly"
                                value={database.engine || ''}
                                className="mt-1 block w-full bg-gray-100 dark:bg-gray-800"
                                disabled
                            />
                        </div>

                        <div>
                            <InputLabel htmlFor="db_user" value="Database User" className="my-2" />
                            <TextInput
                                id="db_user"
                                name="db_user"
                                value={database.db_user}
                                className="mt-1 block w-full bg-gray-100 dark:bg-gray-800"
                                disabled
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="db_password"
                                value="Database Password (leave blank to keep current)"
                                className="my-2"
                            />
                            <TextInput
                                id="db_password"
                                name="db_password"
                                type="password"
                                value={data.db_password}
                                onChange={(e) => setData('db_password', e.target.value)}
                                className="mt-1 block w-full"
                                placeholder="Enter new password or leave blank"
                            />
                            <InputError message={errors.db_password} className="mt-2" />
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
                                    disabled={loading}
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
                                    disabled={loading}
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
                                    value={data.encoding}
                                    onChange={(e) => setData('encoding', e.target.value)}
                                    className="mt-1 block w-full"
                                    disabled={loading}
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
                                    value={data.locale}
                                    onChange={(e) => setData('locale', e.target.value)}
                                    className="mt-1 block w-full"
                                    disabled={loading}
                                />
                                <InputError message={errors.locale} className="mt-2" />
                            </div>
                        )}

                        <div className="flex justify-end">
                            <PrimaryButton className="mr-3" disabled={processing || loading}>
                                Update
                            </PrimaryButton>
                            <SecondaryButton onClick={closeModal}>Cancel</SecondaryButton>
                        </div>
                    </div>
                </form>
            </Modal>
        </>
    );
}
