import Modal from '@/Components/Modal';
import { useState } from 'react';
import DangerButton from './DangerButton';
import SecondaryButton from './SecondaryButton';

export default function ConfirmationButton({ children, buttonClassName = '', doAction }) {

    const [showModal, setShowModal] = useState(false);

    const closeModal = () => {
        setShowModal(false);
    };

    return (<>
        <button className={buttonClassName} onClick={() => setShowModal(true)}>
            {children}
        </button>
        <Modal show={showModal} onClose={closeModal} maxWidth="sm">
            <div className="p-4">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Are you sure?
                </h2>

                <div className="mt-2">
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        This action cannot be undone.
                    </p>
                </div>

                <div className="mt-4">
                    <DangerButton onClick={() => {
                        doAction();
                        closeModal();
                    }} className='mr-2'>
                        Yes, I'm sure
                    </DangerButton>

                    <SecondaryButton onClick={() => closeModal()}>
                        Cancel
                    </SecondaryButton>
                </div>

            </div>
        </Modal>
    </>
    );
}
