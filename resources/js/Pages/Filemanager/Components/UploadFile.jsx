import { useState } from "react";
import axios from "axios";
import { toast } from "react-toastify";

import PrimaryButton from "@/Components/PrimaryButton";
import Modal from "@/Components/Modal";
import SecondaryButton from "@/Components/SecondaryButton";

const CHUNK_SIZE = 2 * 1024 * 1024; // 4MB per chunk

const UploadFile = ({ showUploadFile, setShowUploadFile, refreshFiles, path }) => {

    const [file, setFile] = useState(null);
    const [progress, setProgress] = useState(0);

    const uploadFile = async () => {
        if (!file) return;

        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        let uploadedChunks = 0;

        for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
            const start = chunkIndex * CHUNK_SIZE;
            const end = Math.min(start + CHUNK_SIZE, file.size);
            const chunk = file.slice(start, end);

            const formData = new FormData();
            formData.append("file", chunk);
            formData.append("chunkIndex", chunkIndex);
            formData.append("totalChunks", totalChunks);
            formData.append("originalName", file.name);
            formData.append("path", path);

            await axios.post("/filemanager/upload-file", formData);

            uploadedChunks++;
            setProgress(Math.round((uploadedChunks / totalChunks) * 100));
        }

        refreshFiles(path);
        setShowUploadFile(false);
        toast('File uploaded successfully', { type: 'success' });
    };

    return (
        <Modal show={showUploadFile} closeable={true} onClose={() => setShowUploadFile(false)}>
            <div className="p-6">
                <input type="file" onChange={(e) => setFile(e.target.files[0])} />
                <PrimaryButton onClick={uploadFile} className="mr-2">Upload</PrimaryButton>
                <SecondaryButton onClick={() => setShowUploadFile(false)}>Cancel</SecondaryButton>
                <p>Upload Progress: {progress}%</p>
            </div>
        </Modal>
    );
}

export default UploadFile;
