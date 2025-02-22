import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Checkbox from '@/Components/Checkbox';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { FaUserPlus } from 'react-icons/fa6';
import InputRadio from '@/Components/InputRadio';
import { toast } from 'react-toastify';

export default function CreateAccountForm() {
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
        post,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        username: '',
        name: '',
        email: '',
        password: randomPassword(),
        role: 'user',
        domain_limit: null,
        database_limit: null,
        notify: false,
        ssh_access: false,
    });

    const showCreateModal = () => {
        setShowModal(true);
    };

    const createUser = (e) => {
        e.preventDefault();

        post(route('accounts.store'), {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                reset();
                toast("Account created successfully.");
            }
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
                <FaUserPlus className='mr-2' />
                Create Account
            </button>

            <Modal show={showModal} onClose={closeModal}>
                <form onSubmit={createUser} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                        <FaUserPlus className='mr-2' />
                        Create a New Account
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
                                htmlFor="name"
                                value="Username (a-z0-9-_)"
                                className='my-2'
                            />

                            <TextInput
                                id="username"
                                name="username"
                                value={data.username}
                                onChange={(e) =>
                                    setData('username', e.target.value)
                                }
                                className="mt-1 block w-full"
                                placeholder="Username"
                            />

                            <InputError
                                message={errors.username}
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
                                htmlFor="password"
                                value="Password"
                                className='my-2'
                            />

                            <TextInput
                                id="password"
                                name="password"
                                value={data.password}
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                                className="mt-1 block w-full"
                                placeholder="Password"
                            />

                            <InputError
                                message={errors.password}
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

                        <div>
                            <div className='mt-4 flex items-center space-x-4'>
                                <Checkbox
                                    id="notify"
                                    name="notify"
                                    checked={data.notify}
                                    onChange={(e) =>
                                        setData('notify', e.target.checked)
                                    }
                                    className="mr-1"
                                />
                                <div className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Notify user with account logins via email
                                </div>
                            </div>

                            <InputError
                                message={errors.notify}
                                className="mt-2"
                            />
                        </div>

                        <div className="flex justify-end">
                            <PrimaryButton className="mr-3" disabled={processing}>
                                Create Account
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
