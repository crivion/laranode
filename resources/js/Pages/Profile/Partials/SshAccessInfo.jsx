import { usePage } from '@inertiajs/react';
import { CopyToClipboard } from 'react-copy-to-clipboard';
import { toast } from 'react-toastify';
import { FaTerminal, FaRegCopy } from 'react-icons/fa6';

export default function SshAccessInfo({ className = '' }) {
    const { auth, ssh } = usePage().props;

    const command = `ssh ${ssh?.port !== 22 ? `-p ${ssh?.port} ` : ''}${auth.user.systemUsername}@${ssh?.host}`;

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                    <FaTerminal className="mr-2" />
                    SSH & SFTP access
                </h2>

                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Your system user is <span className="font-mono">{auth.user.systemUsername}</span>, not{' '}
                    <span className="font-mono">{auth.user.username}</span>. Sign in with your panel password.
                </p>
            </header>

            <div className="mt-4 flex items-center justify-between rounded-lg bg-gray-100 px-4 py-3 dark:bg-gray-900">
                <code className="text-sm text-gray-800 dark:text-gray-200 break-all">{command}</code>

                <CopyToClipboard text={command} onCopy={() => toast.success('Command copied')}>
                    <button type="button" className="ml-4 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300" title="Copy">
                        <FaRegCopy />
                    </button>
                </CopyToClipboard>
            </div>

            {!auth.user.ssh_access && (
                <p className="mt-2 text-sm text-amber-600 dark:text-amber-500">
                    Shell access is currently disabled for your account, so this will be refused until an admin enables it.
                </p>
            )}
        </section>
    );
}
