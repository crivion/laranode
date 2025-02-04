import { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ImSpinner9 } from "react-icons/im";
import { VscFileSubmodule } from "react-icons/vsc";
import { FaFolderClosed } from "react-icons/fa6";
import { RiFolderReceivedLine } from "react-icons/ri";
import { FileIcon, defaultStyles } from 'react-file-icon';
import { ToastContainer, toast } from 'react-toastify';
import Checkbox from '@/Components/Checkbox';
import CreateFile from './Components/CreateFile';
import EditFile from './Components/EditFile';
import DeleteFiles from './Components/DeleteFiles';
import { LuFolderPlus } from "react-icons/lu";
import { LuFilePlus2 } from "react-icons/lu";
import { LuDelete } from "react-icons/lu";
import { MdOutlineDriveFileRenameOutline } from "react-icons/md";
import { IoMdCut } from "react-icons/io";
import { MdCopyAll } from "react-icons/md";
import { BiPaste } from "react-icons/bi";
import { TbFileTypeZip } from "react-icons/tb";
import { VscFileZip } from "react-icons/vsc";
import { FaUpload } from "react-icons/fa";


const Filemanager = () => {

    const [files, setFiles] = useState([]);
    const [path, setPath] = useState("/");
    const [goBack, setGoBack] = useState(false);
    const [spinner, showSpinner] = useState(true);
    const [selectedPaths, setSelectedPaths] = useState([]);
    const [createFileType, setCreateFileType] = useState(false);
    const [editFile, setEditFile] = useState(false);
    const [showConfirmDelete, setShowConfirmDelete] = useState(false);

    useEffect(() => {
        cdIntoPath(path);
    }, [editFile]);

    const cdIntoPath = async (path) => {
        setPath(path);
        showSpinner(true);

        try {
            const response = await fetch(`/filemanager/get-directory-contents?path=${path}`);

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


    const handleFileClick = (file) => {
        setSelectedPaths((prevSelected) =>
            prevSelected.includes(file.path)
                ? prevSelected.filter((path) => path !== file.path)
                : [...prevSelected, file.path]
        );
    };

    const handleDoubleClick = (file) => {
        if (file.type == "dir") {
            cdIntoPath(file.path);
        }

        // do not edit images/videos based on extensions
        const extension = file.path.split('.').pop();
        const imagesAndVideos = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mp3', 'webm', 'wav', 'ogg', 'flac', 'mkv', 'mov', 'avi', 'wmv', 'm4v'];

        if (file.type == "file" && !imagesAndVideos.includes(extension)) {
            setEditFile(file.path);
        }
    }

    const formatBytes = (bytes, decimals = 2) => {
        if (bytes === 0) return "0 B";
        const sizes = ["B", "KB", "MB", "GB", "TB"];
        const factor = Math.floor(Math.log(bytes) / Math.log(1024));
        return `${parseFloat((bytes / Math.pow(1024, factor)).toFixed(decimals))} ${sizes[factor]}`;
    };

    const confirmDelete = () => {
        setShowConfirmDelete(true);
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
                        <div className="text-gray-600 dark:text-gray-400 text-sm">Loading files list...</div>
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

                    <div className="mb-5 flex items-center space-x-2 text-gray-600 dark:text-gray-300">
                        <div className="flex flex-col lg:flex-row lg:items-center lg:space-x-2 space-y-2 lg:space-y-0 font-semibold">
                            <button onClick={() => alert('TBD')} className="flex items-center hover:text-indigo-600">
                                <FaUpload className="mr-1" />
                                Upload
                            </button>

                            <button onClick={() => setCreateFileType('directory')} className="flex items-center hover:text-indigo-600">
                                <LuFolderPlus className="mr-1" />
                                Directory
                            </button>
                            <button onClick={() => setCreateFileType('file')} className="flex items-center hover:text-indigo-600">
                                <LuFilePlus2 className="mr-1" />
                                File
                            </button>

                            <button onClick={() => confirmDelete()} className="flex items-center hover:text-red-600 disabled:opacity-25" disabled={selectedPaths.length == 0}>
                                <LuDelete className="mr-1" />
                                Delete
                            </button>

                            <button onClick={() => alert('TBD')} className="flex items-center hover:text-indigo-600 disabled:opacity-25" disabled={selectedPaths.length != 1}>
                                <MdOutlineDriveFileRenameOutline className="mr-1" />
                                Rename
                            </button>

                            <button onClick={() => alert('TBD')} className="flex items-center hover:text-indigo-600 disabled:opacity-25" disabled={selectedPaths.length == 0}>
                                <IoMdCut className="mr-1" />
                                Cut
                            </button>

                            <button onClick={() => alert('TBD')} className="flex items-center hover:text-indigo-600 disabled:opacity-25" disabled={selectedPaths.length == 0}>
                                <MdCopyAll className="mr-1" />
                                Copy
                            </button>

                            {/* here make it if cutFiles == true} */}
                            <button onClick={() => alert('TBD')} className="flex items-center hover:text-indigo-600 disabled:opacity-25" disabled={selectedPaths.length == 0}>
                                <BiPaste className="mr-1" />
                                Paste
                            </button>

                            <button onClick={() => alert('TBD')} className="flex items-center hover:text-indigo-600 disabled:opacity-25" disabled={selectedPaths.length == 0}>
                                <TbFileTypeZip className="mr-1" />
                                Zip
                            </button>

                            {/* here make it if its only one file selected and it ends in .zip*/}
                            <button onClick={() => alert('TBD')} className="flex items-center hover:text-indigo-600 disabled:opacity-25" disabled={selectedPaths.length != 1}>
                                <VscFileZip className="mr-1" />
                                Extract
                            </button>
                        </div>

                    </div>

                    {goBack && goBack != "" && (<div className='flex items-center space-x-2 text-xs'>
                        <div className="bg-white dark:bg-gray-850 py-3 px-6  hover:bg-gray-200 dark:hover:bg-gray-800">
                            <button className="dark:text-gray-300 text-gray-900 flex items-center space-x-2" onDoubleClick={() => cdIntoPath(goBack)}>
                                <RiFolderReceivedLine className="text-gray-500 dark:text-gray-300 mr-1" />
                                Back
                            </button>
                        </div>
                        <div className="bg-white dark:bg-gray-850 py-3 px-6 dark:text-gray-300 text-gray-900 flex items-center space-x-2">
                            Path: {path}
                        </div>
                    </div>
                    )}


                    {files.sort((a, b) => {
                        if (a.type === 'dir' && b.type !== 'dir') return -1;
                        if (a.type !== 'dir' && b.type === 'dir') return 1;
                        return 0;
                    }).map((file, index) => (
                        <div
                            key={`file-${index}`}
                            className={`flex items-center font-bold text-gray-900 dark:text-gray-300 py-3 px-6 border-b dark:border-b-gray-800 space-x-2 ${selectedPaths.includes(file.path)
                                ? 'bg-gray-200 text-sky-700 hover:text-sky-700 hover:bg-gray-100 dark:hover:bg-gray-700 dark:hover:text-sky-600 dark:bg-gray-800 dark:text-sky-600'
                                : 'bg-white hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800'
                                }`}
                            onDoubleClick={() => handleDoubleClick(file)}
                        >
                            <div>
                                <Checkbox checked={selectedPaths.includes(file.path)} onChange={(e) => handleFileClick(file)} className="-mt-1 mr-1" />
                            </div>
                            {file.type === "dir" ? (
                                <div>
                                    <FaFolderClosed className={`text-gray-500 ${selectedPaths.includes(file.path) ? 'text-sky-600' : ''} cursor-pointer`} />
                                </div>
                            ) : (
                                <div className="w-4 h-4">
                                    <FileIcon extension={file.path.split('.').pop()} {...defaultStyles[file.path.split('.').pop()]} className="cursor-pointer" />
                                </div>
                            )}

                            <div className="text-sm cursor-pointer flex-grow">
                                {/* remove path from file.path */}
                                {file.path.split('/').pop()}
                            </div>

                            <div className="text-xs text-gray-400 dark:text-gray-600">
                                {typeof file.file_size == "undefined" ? "--" : formatBytes(file.file_size)}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <CreateFile
                path={path}
                fileType={createFileType}
                setCreateFileType={setCreateFileType}
                refreshFiles={cdIntoPath}
            />

            <EditFile
                editFile={editFile}
                setEditFile={setEditFile}
            />

            <DeleteFiles
                files={selectedPaths}
                setSelectedPaths={setSelectedPaths}
                showConfirmDelete={showConfirmDelete}
                setShowConfirmDelete={setShowConfirmDelete}
                refreshFiles={cdIntoPath}
                path={path}
            />

        </AuthenticatedLayout >
    );
}

export default Filemanager
