import { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ImSpinner9 } from "react-icons/im";
import { VscFileSubmodule } from "react-icons/vsc";
import { FaFolderClosed } from "react-icons/fa6";
import { RiFolderReceivedLine } from "react-icons/ri";
import { FileIcon, defaultStyles } from 'react-file-icon';
import { ToastContainer, toast } from 'react-toastify';
import { MdInfoOutline } from "react-icons/md";


const Filemanager = () => {

    const [files, setFiles] = useState([]);
    const [path, setPath] = useState([]);
    const [goBack, setGoBack] = useState(false);
    const [spinner, showSpinner] = useState(true);

    useEffect(() => {
        cdIntoPath('/');
    }, []);

    const cdIntoPath = async (path) => {
        setPath(path);
        showSpinner(true);

        try {
            const response = await fetch(`/filemanager/get-contents?path=${path}`);

            if (!response.ok) {
                const errorData = await response.json();
                const errorMessage = errorData.error || response.statusText;
                toast(errorMessage, { type: 'error' });
                showSpinner(false);
                return;
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;

                // Decode the chunk and append to the buffer
                buffer += decoder.decode(value, { stream: true });
            }

            const json = JSON.parse(buffer.trim());
            setFiles(json.files);
            setGoBack(json.goBack);

        } catch (error) {
            // Handle network errors or other exceptions
            toast(error.message, { type: 'error' });
        } finally {
            showSpinner(false);
        }
    };


    const cdIntoPathOK = async (path) => {
        setPath(path);
        showSpinner(true);

        const response = await fetch(`/filemanager/get-contents?path=${path}`);

        if (!response.ok) {
            // Parse the error message from the response body
            const errorData = await response.json();
            const errorMessage = errorData.error || response.statusText;

            // Display the error message in a toast
            toast(errorMessage, { type: 'error' });
            showSpinner(false);
            return;
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let chunks = '';

        // Read the stream progressively
        while (true) {
            const { value, done } = await reader.read();
            if (done) break;

            try {
                chunks += decoder.decode(value, { stream: true });

                const data = JSON.parse(chunks);
                setFiles(data.files);
                setGoBack(data.goBack);

                console.log(data);
            } catch (error) {
                console.log(error.toString());
                toast(error.toString(), { type: 'error' });
            }

        }

        showSpinner(false);
    };

    if (spinner) {
        return (
            <AuthenticatedLayout
                header={
                    <div className="flex flex-col xl:justify-between xl:flex-row">
                        <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                            <VscFileSubmodule className='mr-2' />
                            Filemanager
                        </h2>
                        <ToastContainer />
                    </div>
                }
            >
                <div className="max-w-7xl bg-white dark:bg-gray-950 py-6 sm:px-6 lg:px-8">
                    <div className="mt-8 flex items-center space-x-2">
                        <div>
                            <ImSpinner9 className="animate-spin w-5 h-5 mr-2" />
                        </div>
                        <div class="text-gray-600 dark:text-gray-400 text-sm">Loading files list...</div>
                    </div>
                </div>
            </AuthenticatedLayout>
        )
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col xl:justify-between xl:flex-row">
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                        <VscFileSubmodule className='mr-2' />
                        Filemanager
                    </h2>
                    <ToastContainer />
                </div>
            }
        >


            <div className="max-w-7xl">
                <div className="mt-8 px-4">

                    <div className="text-xs mb-5 flex items-center space-x-2 text-gray-600 dark:text-gray-400">
                        <MdInfoOutline className="text-gray-500 w-5 h-5 flex-shrink-0 mr-1" /> Double-Click on a directory icon to enter / cd into it
                    </div>

                    {goBack && goBack != "" && (
                        <div className="bg-white dark:bg-gray-850 shadow py-3 px-6">

                            <button className="dark:text-gray-300 text-gray-900 flex items-center space-x-2" onClick={() => cdIntoPath(goBack)}>
                                <RiFolderReceivedLine className="text-gray-500 dark:text-gray-300 w-5 h-5 flex-shrink-0 mr-1" />
                                Back
                            </button>

                        </div>
                    )}


                    {files.sort((a, b) => {
                        if (a.type === 'dir' && b.type !== 'dir') return -1;
                        if (a.type !== 'dir' && b.type === 'dir') return 1;
                        return 0;
                    }).map((file, index) => (
                        <div key={`file-${index}`} className="flex items-center font-bold text-gray-900 dark:text-gray-300 py-3 px-6 bg-white dark:bg-gray-850 hover:bg-gray-100 dark:hover:bg-gray-800 shadow space-x-2">
                            {file.type === "dir" ? (<>
                                <button className="" onDoubleClick={() => cdIntoPath(file.path)}>
                                    <FaFolderClosed className="text-gray-500 w-5 h-5 flex-shrink-0 mr-1" />
                                </button>
                            </>
                            ) : (
                                <div className="w-5 h-5">
                                    <FileIcon extension={file.path.split('.').pop()} {...defaultStyles[file.path.split('.').pop()]} className="mr-1" />
                                </div>
                            )}

                            <div className="text-center text-sm -1.5">
                                {file.path}
                            </div>
                        </div>
                    ))}
                </div>
            </div>


        </AuthenticatedLayout >
    );
}

export default Filemanager
