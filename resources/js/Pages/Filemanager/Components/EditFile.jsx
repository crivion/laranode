import PrimaryButton from "@/Components/PrimaryButton"
import SecondaryButton from "@/Components/SecondaryButton"
import InputLabel from "@/Components/InputLabel"
import Modal from "@/Components/Modal"
import { ImSpinner9 } from "react-icons/im";
import { useEffect, useState } from "react"
import { toast } from "react-toastify"


const EditFile = ({ editFile, setEditFile }) => {


    const [fileContents, setFileContents] = useState('');
    const [showSpinner, setShowSpinner] = useState(true);

    useEffect(() => {
        readFile();
    }, []);

    const readFile = async () => {

        setShowSpinner(true);

        if (!editFile) {
            return;
        }

        try {
            const response = await fetch(`/filemanager/get-file-contents?file=${editFile}`);

            if (!response.ok) {
                const errorData = await response.json();
                const errorMessage = errorData.error || response.statusText;
                toast(errorMessage, { type: 'error' });
                return;
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();

            // let buffer = '';
            while (true) {
                const { value, done } = await reader.read();
                if (done) break;

                // Decode the chunk and append to the buffer
                // buffer = decoder.decode(value, { stream: true });
                setFileContents(prev => prev + decoder.decode(value, { stream: true }));

            }


        } catch (error) {
            toast('Error reading file', { type: 'error' })
            toast(error.message, { type: 'error' })
            console.error(error)
        } finally {
            setShowSpinner(false);
        }
    };


    const updateFile = (e) => {
        e.preventDefault()

        window.axios.patch('/filemanager/update-file-contents', { editFile, fileContents }).then((response) => {
            toast(response.data.message, { type: 'success' })
            clearFileContentsAndClose();
        }).catch((error) => {
            toast(error.message, { type: 'error' })
            console.log(error);
        })

    };

    const clearFileContentsAndClose = () => {
        setFileContents('');
        setShowSpinner(true);
        setEditFile(false);
    }

    if (!editFile) {
        return;
    }

    return (
        <>
            <Modal show={editFile} closeable={true} onClose={() => clearFileContentsAndClose()}>
                <form onSubmit={updateFile} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                        {showSpinner && <ImSpinner9 className="mr-2 animate-spin" />}
                        Editing {editFile}
                    </h2>

                    <div className="mt-6">
                        <InputLabel
                            htmlFor="fileName"
                            value="fileNmae"
                            className="sr-only"
                        />

                        <textarea
                            disabled={showSpinner}
                            id="fileName"
                            name="fileName"
                            value={fileContents}
                            onChange={(e) =>
                                setFileContents(e.target.value)
                            }
                            className="w-full p-2 rounded shadow border border-gray-400 min-h-[300px] max-h-[500px] bg-white text-gray-700 text-sm dark:bg-gray-800 dark:text-gray-200"
                            autoFocus={true}
                        />


                    </div>

                    <div className="mt-6 flex justify-end">
                        <PrimaryButton className="mr-3" onClick={(e) => updateFile(e)}>
                            Save Changes
                        </PrimaryButton>

                        <SecondaryButton onClick={() => setEditFile(false)}>
                            Cancel
                        </SecondaryButton>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default EditFile

