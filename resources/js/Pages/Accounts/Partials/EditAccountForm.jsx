import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Checkbox from '@/Components/Checkbox';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FaEdit, FaUserEdit } from 'react-icons/fa';
import InputRadio from '@/Components/InputRadio';
import { router } from '@inertiajs/react'

export default function EditAccountForm({ account }) {
    const [showModal, setShowModal] = useState(false);

    const randomPassword = () => {
        const characters =
            'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        const charactersLength = characters.length;
        let result = '';
        for (let i = 0; i < 12; i++) {
            result += characters.charAt(
                Math.floor(Math.random() * charactersLength)
            );
        }
        return result;
    };

    const {
        data,
        setData,
        patch,
        processing,
        errors,
        clearErrors,
    } = useForm({
        id: account.id,
        name: account.name,
        email: account.email,
        role: account.role,
        domain_limit: account.domain_limit,
        database_limit: account.database_limit,
        ssh_access: account.ssh_access,
        new_password: '',
    });

    const showEditModal = () => {
        setShowModal(true);
    };

    const updateUser = (e) => {
        e.preventDefault();

        patch(route('accounts.update', { account: account.id }), {
            onSuccess: () => {
                router.visit(route('accounts.index'));
            },
        });
    };

    const closeModal = () => {
        setShowModal(false);
        clearErrors();
    };


    if (!account) return;

    return (
        <>
            <button onClick={showEditModal} className='flex items-center text-gray-700 dark:text-gray-300'>
                <FaEdit className='mr-2' />
            </button>

            <Modal show={showModal} onClose={closeModal}>
                <form onSubmit={updateUser} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                        <FaUserEdit className='mr-2' />
                        Edit Account
                    </h2>

                    <div className="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-4 max-h-[500px] overflow-scroll">

                        <div>
                            <InputLabel
                                htmlFor="name"
                                value="Name"
                                className='my-2'
                            />

                            <TextInput
                                id="name"
                                name="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                className="mt-1 block w-full"
                                isFocused
                                placeholder="Full Name"
                            />

                            <InputError
                                message={errors.name}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="email"
                                value="Email"
                                className='my-2'
                            />

                            <TextInput
                                id="email"
                                name="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                className="mt-1 block w-full"
                                placeholder="Email"
                            />

                            <InputError
                                message={errors.email}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="new_password"
                                value="New Password (leave empty to keep current)"
                                className='my-2'
                            />

                            <TextInput
                                id="new_password"
                                name="new_password"
                                onChange={(e) =>
                                    setData('new_password', e.target.value)
                                }
                                className="mt-1 block w-full"
                                placeholder="New Password"
                            />

                            <InputError
                                message={errors.new_password}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="role"
                                value="Role"
                                className='my-2'
                            />

                            <div className='flex items-center space-x-4'>
                                <InputRadio
                                    id="admin"
                                    name="admin"
                                    checked={data.role == 'admin'}
                                    onChange={(e) =>
                                        setData('role', e.target.checked ? 'admin' : 'user')
                                    }
                                    className="h-4 w-4"
                                    value="admin"
                                />
                                <span className="text-sm text-gray-600 dark:text-gray-400">
                                    Admin
                                </span>

                                <InputRadio
                                    id="user"
                                    name="user"
                                    value={'user'}
                                    checked={data.role == 'user'}
                                    onChange={(e) =>
                                        setData('role', e.target.checked ? 'user' : 'admin')
                                    }
                                    className="h-4 w-4"
                                />
                                <span className="text-sm text-gray-600 dark:text-gray-400">
                                    User
                                </span>
                            </div>

                            <InputError
                                message={errors.role}
                                className="mt-2"
                            />
                        </div>


                        <div>
                            <InputLabel
                                htmlFor="domain_limit"
                                value="Domain Limit (leave empty for unlimited)"
                                className='my-2'
                            />

                            <TextInput
                                id="domain_limit"
                                name="domain_limit"
                                type="number"
                                value={data.domain_limit}
                                onChange={(e) =>
                                    setData('domain_limit', e.target.value)
                                }
                                className="mt-1 block w-full"
                                placeholder="Domain Limit"
                            />

                            <InputError
                                message={errors.domain_limit}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="database_limit"
                                value="Database Limit (leave empty for unlimited)"
                                className='my-2'
                            />

                            <TextInput
                                id="database_limit"
                                name="database_limit"
                                type="number"
                                value={data.database_limit}
                                onChange={(e) =>
                                    setData('database_limit', e.target.value)
                                }
                                className="mt-1 block w-full"
                                placeholder="Database Limit"
                            />

                            <InputError
                                message={errors.database_limit}
                                className="mt-2"
                            />
                        </div>

                        <div className='mt-4 flex items-center space-x-4'>
                            <Checkbox
                                id="ssh"
                                name="ssh"
                                checked={data.ssh_access}
                                onChange={(e) =>
                                    setData('ssh_access', e.target.checked)
                                }
                                className="mr-1"
                            />
                            <div className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Allow SSH/SFTP access
                            </div>
                        </div>

                        <div className="flex justify-end">
                            <PrimaryButton className="mr-3" disabled={processing}>
                                Update Account
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
