import PrimaryButton from "@/Components/PrimaryButton"
import SecondaryButton from "@/Components/SecondaryButton"
import InputLabel from "@/Components/InputLabel"
import Modal from "@/Components/Modal"
import TextInput from "@/Components/TextInput"
import { useState } from "react"
import { toast } from "react-toastify"

const CreateFile = ({ path, fileType, setCreateFileType, refreshFiles }) => {

    const [file, setFile] = useState('');

    const createFile = (e) => {
        e.preventDefault()

        console.log(`create ${fileType} with name ${file} clicked`);

        window.axios.post('/filemanager/create-file', { path, fileName: file, fileType }).then((response) => {
            toast(response.data.message, { type: 'success' })
            setCreateFileType(false)
            refreshFiles(path);
        }).catch((error) => {
            toast('Error', { type: 'error' })
            console.log(error);
        })

    };

    if (!fileType) {
        return;
    }

    return (
        <>
            <Modal show={fileType} closeable={true} onClose={() => setCreateFileType(false)}>
                <form onSubmit={createFile} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Create a new {fileType} in {path}
                    </h2>

                    <div className="mt-6">
                        <InputLabel
                            htmlFor="password"
                            value="Password"
                            className="sr-only"
                        />

                        <TextInput
                            id="fileName"
                            name="fileName"
                            onChange={(e) =>
                                setFile(e.target.value)
                            }
                            className="mt-1 block w-3/4"
                            isFocused
                            placeholder={`${fileType} Name`}
                        />
                    </div>

                    <div className="mt-6 flex justify-end">
                        <PrimaryButton className="mr-3" onClick={createFile}>
                            Create {fileType}
                        </PrimaryButton>

                        <SecondaryButton onClick={() => setCreateFileType(false)}>
                            Cancel
                        </SecondaryButton>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default CreateFile
