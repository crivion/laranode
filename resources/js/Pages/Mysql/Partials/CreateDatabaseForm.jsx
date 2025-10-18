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
import axios from 'axios';

export default function CreateDatabaseForm() {
    const { auth } = usePage().props;
    const [showModal, setShowModal] = useState(false);
    const [charsets, setCharsets] = useState([]);
    const [collations, setCollations] = useState([]);
    const [filteredCollations, setFilteredCollations] = useState([]);
    const [loading, setLoading] = useState(false);

    const { data, setData, post, processing, reset, clearErrors, errors, transform } = useForm({
        name_suffix: '',
        db_user_suffix: '',
        db_pass: '',
        charset: 'utf8mb4',
        collation: 'utf8mb4_unicode_ci',
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
            
            // Set default collation for utf8mb4 if not already set
            if (data.charset === 'utf8mb4' && !data.collation) {
                const utf8mb4Collations = response.data.collations.filter(c => c.charset === 'utf8mb4');
                const defaultCollation = utf8mb4Collations.find(c => c.default === 'Yes') || utf8mb4Collations.find(c => c.name === 'utf8mb4_unicode_ci') || utf8mb4Collations[0];
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

    const showCreateModal = () => setShowModal(true);

    const closeModal = () => {
        setShowModal(false);
        clearErrors();
        reset();
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

        post(route('mysql.store'), {
            preserveScroll: true,
            onSuccess: closeModal,
            onFinish: () => transform((form) => form), // reset transform
        });
    };

    const prefix = auth.user.username + '_';

    return (
        <>
            <button onClick={showCreateModal} className='flex items-center text-gray-700 dark:text-gray-300'>
                <TbDatabase className='mr-2' />
                Create Database
            </button>

            <Modal show={showModal} onClose={closeModal}>
                <form onSubmit={createDatabase} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                        <TbDatabase className='mr-2' />
                        Add a New Database
                    </h2>

                    <div className="mt-6 flex flex-col space-y-4 max-h-[500px]">
                        <div>
                            <InputLabel htmlFor="name_suffix" value="Database name" className='my-2' />
                            <div className="mt-1 flex">
                                <span className="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 text-sm">
                                    {prefix}
                                </span>
                                <TextInput id="name_suffix" name="name_suffix" value={data.name_suffix} onChange={(e) => setData('name_suffix', e.target.value)} className="flex-1 rounded-l-none" placeholder={'mydb'} required />
                            </div>
                            <InputError message={errors.name} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="db_user_suffix" value="Database user" className='my-2' />
                            <div className="mt-1 flex">
                                <span className="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 text-sm">
                                    {prefix}
                                </span>
                                <TextInput id="db_user_suffix" name="db_user_suffix" value={data.db_user_suffix} onChange={(e) => setData('db_user_suffix', e.target.value)} className="flex-1 rounded-l-none" placeholder={'user'} required />
                            </div>
                            <InputError message={errors.db_user} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="db_pass" value="Database password" className='my-2' />
                            <TextInput id="db_pass" name="db_pass" type="password" value={data.db_pass} onChange={(e) => setData('db_pass', e.target.value)} className="mt-1 block w-full" required />
                            <InputError message={errors.db_pass} className="mt-2" />
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
                            <PrimaryButton className="mr-3" disabled={processing}>Add Database</PrimaryButton>
                            <SecondaryButton onClick={closeModal}>Cancel</SecondaryButton>
                        </div>
                    </div>
                </form>
            </Modal>
        </>
    );
}


