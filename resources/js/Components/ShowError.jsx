import { IoIosWarning } from "react-icons/io";

const ShowError = ({ error }) => {
    return (
        <div className="my-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative flex items-center space-x-2" role="alert">
            <span><IoIosWarning className="w-5 h-5" /></span>
            <span>{error}</span>
        </div>
    )
}

export default ShowError
