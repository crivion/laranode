import { useState } from 'react';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { router } from '@inertiajs/react';
import { toast } from 'react-toastify';
import { TbBrandPhp } from 'react-icons/tb';

export default function InstallPHPForm({ installedVersions = [] }) {
    const [showModal, setShowModal] = useState(false);
    const [version, setVersion] = useState('');
    const [isInstalling, setIsInstalling] = useState(false);

    const availableVersions = ['8.4', '8.3', '8.2', '8.1', '8.0', '7.4'];

    const handleInstall = () => {
        if (!version) {
            toast.error('Please select a PHP version');
            return;
        }

        setIsInstalling(true);

        router.post(route('php.install'), 
            { version },
            {
                onBefore: () => toast('Installing PHP ' + version + '...'),
                onSuccess: () => {
                    toast.success('PHP ' + version + ' installed successfully');
                    setShowModal(false);
                    setVersion('');
                    router.reload();
                },
                onError: (errors) => {
                    toast.error('Failed to install PHP ' + version);
                    console.error(errors);
                },
                onFinish: () => setIsInstalling(false),
            }
        );
    };

    return (
        <>
            <PrimaryButton onClick={() => setShowModal(true)}>
                <TbBrandPhp className="mr-2" />
                Install New Version
            </PrimaryButton>

            <Modal show={showModal} onClose={() => !isInstalling && setShowModal(false)}>
                <div className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Install PHP Version
                    </h2>

                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Select a PHP version to install. This will install PHP-FPM and common extensions.
                    </p>

                    <div className="mt-6">
                        <label htmlFor="php-version" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            PHP Version
                        </label>
                        <select
                            id="php-version"
                            value={version}
                            onChange={(e) => setVersion(e.target.value)}
                            disabled={isInstalling}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white sm:text-sm"
                        >
                            <option value="">Select a version</option>
                            {availableVersions.map((v) => {
                                const isInstalled = installedVersions.some((p) => p.version === v);
                                return (
                                    <option key={v} value={v} disabled={isInstalled}>
                                        PHP {v}{isInstalled ? ' (installed)' : ''}
                                    </option>
                                );
                            })}
                        </select>
                    </div>

                    <div className="mt-6 flex justify-end space-x-3">
                        <SecondaryButton onClick={() => setShowModal(false)} disabled={isInstalling}>
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton onClick={handleInstall} disabled={isInstalling || !version}>
                            {isInstalling ? 'Installing...' : 'Install'}
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>
        </>
    );
}
