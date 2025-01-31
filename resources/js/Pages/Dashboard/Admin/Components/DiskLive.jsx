import { FaBuffer, FaHardDrive } from "react-icons/fa6";
import { ImSpinner9 } from "react-icons/im";
import { GiPenguin, GiProgression } from "react-icons/gi";
import { MdOutlineSummarize } from "react-icons/md";

const DiskLive = ({ diskStats }) => {

    return (
        <div className="bg-white dark:bg-gray-850 p-6 rounded-lg shadow-md">

            <div class="flex items-center space-x-2">
                <div>
                    <FaHardDrive className="text-purple-500 w-5 h-5 flex-shrink-0" />
                </div>

                <div className="text-gray-600 dark:text-gray-400 text-lg flex-grow">Disk Usage at /</div>
            </div>

            <div className="flex flex-col space-y-1 mt-3">
                <p className=" font-bold text-gray-900 dark:text-gray-300 flex items-center">
                    <GiProgression className="text-lime-500 dark:text-lime-200 text-3xl w-3 h-3 flex-shrink-0 mr-1" />
                    Used: {diskStats?.used ? (
                        diskStats.used
                    ) : (
                        <ImSpinner9 className="ml-1 animate-spin w-3 h-3" />
                    )}
                </p>
                <p className=" font-bold text-gray-900 dark:text-gray-300 flex items-center">
                    <GiPenguin className="text-indigo-500 text-3xl w-3 h-3 flex-shrink-0 mr-1" />
                    Free: {diskStats?.free ? (
                        diskStats.free
                    ) : (
                        <ImSpinner9 className="ml-1 animate-spin w-3 h-3" />
                    )}
                </p>
                <p className=" font-bold text-gray-900 dark:text-gray-300 flex items-center">
                    <FaBuffer className="text-orange-300 text-3xl w-3 h-3 flex-shrink-0 mr-1" />
                    Size: {diskStats?.size ? (
                        diskStats.size
                    ) : (
                        <ImSpinner9 className="ml-1 animate-spin w-3 h-3" />
                    )}
                </p>
                <p className=" font-bold text-gray-900 dark:text-gray-300 flex items-center">
                    <MdOutlineSummarize className="text-pink-400 text-3xl w-3 h-3 flex-shrink-0 mr-1" />
                    Percent Used : {diskStats?.percent ? (
                        diskStats.percent
                    ) : (
                        <ImSpinner9 className="ml-1 animate-spin w-3 h-3" />
                    )}
                </p>
            </div>
        </div>
    );
}

export default DiskLive
