import { useState } from "react";
import InputLabel from "@/Components/InputLabel"
import Modal from "@/Components/Modal"
import TextInput from "@/Components/TextInput"
import PrimaryButton from "@/Components/PrimaryButton"
import SecondaryButton from "@/Components/SecondaryButton";
import { toast } from "react-toastify";

const RenameFile = ({ selectedFile, setSelectedPaths, renameFile, setRenameFile, refreshFiles, path }) => {

    const [newName, setNewName] = useState('');

    const confirmRename = (e) => {
        e.preventDefault();

        if (!selectedFile) {
            toast('No file selected', { type: 'error' });
            return;
        }


        window.axios.patch('/filemanager/rename-file', { currentName: selectedFile, newName }).then((response) => {
            toast(response.data.message, { type: 'success' });
            closeModal();
        }).catch((error) => {
            if (error?.response?.data?.error) {
                toast(error.response.data.error, { type: 'error' });
            } else {
                toast(error.message, { type: 'error' });
            }
        });

    };

    const closeModal = () => {
        setSelectedPaths([]);
        refreshFiles(path);
        setRenameFile(false);
    };


    return (
        <Modal show={renameFile} closeable={true} onClose={() => closeModal()}>
            <div className="p-6 text-gray-700 dark:text-gray-200">

                {!selectedFile && (<div className='text-red-500'>No file selected</div>)}

                <form onSubmit={confirmRename} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Rename {selectedFile}
                    </h2>

                    <div className="mt-6">
                        <InputLabel
                            htmlFor="file"
                            value="file"
                            className="sr-only"
                        />

                        <TextInput
                            id="newFilename"
                            name="newFilename"
                            onChange={(e) =>
                                setNewName(e.target.value)
                            }
                            value={newName}
                            className="mt-1 block w-3/4"
                            isFocused
                            placeholder={`New Name for ${selectedFile}`}
                        />
                    </div>

                    <div className="mt-6 flex justify-end">
                        <PrimaryButton className="mr-3" onClick={(e) => confirmRename(e)}>
                            Save
                        </PrimaryButton>

                        <SecondaryButton onClick={() => setRenameFile(false)}>
                            Cancel
                        </SecondaryButton>
                    </div>
                </form>

            </div>
        </Modal>
    );

}

export default RenameFile
