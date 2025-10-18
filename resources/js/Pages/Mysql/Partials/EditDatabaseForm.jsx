import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import SearchableDropdown from '@/Components/SearchableDropdown';
import { useForm, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { TbDatabase } from 'react-icons/tb';
import { FaEdit } from 'react-icons/fa';
import axios from 'axios';

export default function EditDatabaseForm({ database }) {
    const { auth } = usePage().props;
    const [showModal, setShowModal] = useState(false);
    const [charsets, setCharsets] = useState([]);
    const [collations, setCollations] = useState([]);
    const [filteredCollations, setFilteredCollations] = useState([]);
    const [loading, setLoading] = useState(false);

    const { data, setData, patch, processing, reset, clearErrors, errors } = useForm({
        id: database.id || 0,
        charset: database.charset || 'utf8mb4',
        collation: database.collation || 'utf8mb4_unicode_ci',
        db_password: '',
    });

    useEffect(() => {
        if (showModal) {
            fetchCharsetsAndCollations();
        }
    }, [showModal]);

    useEffect(() => {
        // Filter collations based on selected charset
        if (data.charset && collations.length > 0) {
            const filtered = collations.filter(collation => collation.charset === data.charset);
            setFilteredCollations(filtered);

            // If current collation is not valid for the selected charset, set to default
            if (data.collation && !filtered.find(c => c.name === data.collation)) {
                // Find the default collation for this charset
                const defaultCollation = filtered.find(c => c.default === 'Yes') || filtered[0];
                if (defaultCollation) {
                    setData('collation', defaultCollation.name);
                }
            }
        } else {
            setFilteredCollations(collations);
        }
    }, [data.charset, collations]);

    const fetchCharsetsAndCollations = async () => {
        setLoading(true);
        try {
            const response = await axios.get(route('mysql.charsets-collations'));
            setCharsets(response.data.charsets);
            setCollations(response.data.collations);

            // Set default collation for current charset if not already set
            if (data.charset && !data.collation) {
                const charsetCollations = response.data.collations.filter(c => c.charset === data.charset);
                const defaultCollation = charsetCollations.find(c => c.default === 'Yes') || charsetCollations[0];
                if (defaultCollation) {
                    setData('collation', defaultCollation.name);
                }
            }

            setFilteredCollations(response.data.collations);
        } catch (error) {
            console.error('Error fetching charsets and collations:', error);
        } finally {
            setLoading(false);
        }
    };

    const showEditModal = () => {
        setShowModal(true);
        // Reset form with current database values
        setData({
            id: database.id,
            name: database.name,
            charset: database.charset || 'utf8mb4',
            collation: database.collation || 'utf8mb4_unicode_ci',
        });
    };

    const closeModal = () => {
        setShowModal(false);
        clearErrors();
        reset();
    };

    const updateDatabase = (e) => {
        e.preventDefault();
        patch(route('mysql.update'), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    };

    return (
        <>
            <button
                onClick={showEditModal}
                className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                data-tooltip-id={`tooltip-edit-${database.name}`}
                data-tooltip-content="Edit Charset & Collation"
                data-tooltip-place="top"
            >
                <FaEdit className='w-4 h-4' />
            </button>

            <Modal show={showModal} onClose={closeModal}>
                <form onSubmit={updateDatabase} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                        <TbDatabase className='mr-2' />
                        Edit Database: {database.name}
                    </h2>

                    <div className="mt-6 flex flex-col space-y-4 max-h-[500px]">
                        <div>
                            <InputLabel htmlFor="db_user" value="Database User" className='my-2' />
                            <TextInput
                                id="db_user"
                                name="db_user"
                                value={database.db_user}
                                className="mt-1 block w-full bg-gray-100 dark:bg-gray-800"
                                disabled
                            />
                        </div>
                        <div>
                            <InputLabel htmlFor="db_password" value="Database Password (leave blank to keep current)" className='my-2' />
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
                        <div>
                            <InputLabel htmlFor="charset" value="Charset" className='my-2' />
                            <select
                                id="charset"
                                name="charset"
                                value={data.charset}
                                onChange={(e) => setData('charset', e.target.value)}
                                className="mt-1 block w-full flex-1 border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600 rounded-md"
                                disabled={loading}
                            >
                                {charsets.map(charset => (
                                    <option key={charset.name} value={charset.name}>
                                        {charset.name} - {charset.description}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.charset} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="collation" value="Collation" className='my-2' />
                            <SearchableDropdown
                                options={filteredCollations}
                                value={data.collation}
                                onChange={(collation) => setData('collation', collation.name)}
                                placeholder="Select a collation..."
                                className="mt-1"
                                disabled={loading || filteredCollations.length === 0}
                            />
                            <InputError message={errors.collation} className="mt-2" />
                        </div>
                        <div className="flex justify-end">
                            <PrimaryButton className="mr-3" disabled={processing}>Update</PrimaryButton>
                            <SecondaryButton onClick={closeModal}>Cancel</SecondaryButton>
                        </div>
                    </div>
                </form>
            </Modal>
        </>
    );
}
