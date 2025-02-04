import Modal from "@/Components/Modal";
import DangerButton from "@/Components/DangerButton";
import SecondaryButton from "@/Components/SecondaryButton";
import { toast } from "react-toastify";

const DeleteFiles = ({ files, setSelectedPaths, showConfirmDelete, setShowConfirmDelete, refreshFiles, path }) => {

    const confirmDelete = () => {

        window.axios.post('/filemanager/delete-files', { filesToDelete: files }).then((response) => {
            toast(response.data.message, { type: 'success' });
            setShowConfirmDelete(false);
            setSelectedPaths([]);
            refreshFiles(path);
        }).catch((error) => {
            if (error?.response?.data?.error) {
                toast(error.response.data.error, { type: 'error' });
            } else {
                toast(error.message, { type: 'error' });
            }
        });

    };

    const closeModal = () => {
        setShowConfirmDelete(false);
    };

    return (
        <Modal show={showConfirmDelete} closeable={true} onClose={() => closeModal()}>
            <div class="p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Are you sure you want to delete these files?
                </h2>

                <div className="mt-6 overflow-scroll">
                    <ul>
                        {files.map((file, index) => (
                            <li key={index}>{file}</li>
                        ))}
                    </ul>
                </div>

                <div className="mt-6 flex justify-end">
                    <DangerButton className="mr-3" onClick={() => confirmDelete()}>
                        Yes, delete them!
                    </DangerButton>

                    <SecondaryButton onClick={() => closeModal()}>
                        Cancel
                    </SecondaryButton>
                </div>
            </div>
        </Modal>
    );

}

export default DeleteFiles
