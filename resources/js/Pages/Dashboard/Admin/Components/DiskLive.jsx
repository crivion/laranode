import { FaBuffer, FaHardDrive } from "react-icons/fa6";
import { ImSpinner9 } from "react-icons/im";
import { GiPenguin, GiProgression } from "react-icons/gi";
import { MdOutlineSummarize } from "react-icons/md";

const DiskLive = ({ diskStats }) => {

    return (
        <div className="mt-5">
            <div className="flex items-center space-x-2">
                <div>
                    <FaHardDrive className="text-purple-500 w-5 h-5 flex-shrink-0" />
                </div>

                <div className="text-gray-600 dark:text-gray-400 text-lg">Disk Stats at /</div>
                <div className="bg-gray-200 dark:bg-gray-700 rounded-full text-xs font-semibold text-gray-800 dark:text-gray-200 py-1 px-2 uppercase mr-3">
                    {diskStats?.percent ? (
                        diskStats.percent + " used"
                    ) : (
                        <div className="flex justify-center items-center mt-2">
                            <ImSpinner9 className="animate-spin w-5 h-5" />
                        </div>
                    )}
                </div>

            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                <div className="font-bold text-gray-900 dark:text-gray-300 flex flex-col py-3 px-6 rounded-lg bg-white dark:bg-gray-850 shadow">
                    <div className="flex items-center justify-center text-sm">
                        <GiProgression className="text-lime-500 dark:text-lime-200 w-6 h-6 flex-shrink-0 mr-1" />
                        <div>Used</div>
                    </div>
                    <div className="text-center text-lg mt-1.5">
                        {diskStats?.used ? (
                            diskStats.used
                        ) : (
                            <div className="flex justify-center items-center mt-2">
                                <ImSpinner9 className="animate-spin w-5 h-5" />
                            </div>
                        )}
                    </div>
                </div>

                <div className="font-bold text-gray-900 dark:text-gray-300 flex flex-col py-3 px-6 rounded-lg bg-white dark:bg-gray-850 shadow">
                    <div className="flex items-center justify-center text-sm">
                        <GiPenguin className="text-indigo-500 w-6 h-6 flex-shrink-0 mr-1" />
                        <div>Free</div>
                    </div>
                    <div className="text-center text-lg mt-1.5">
                        {diskStats?.free ? (
                            diskStats.free
                        ) : (
                            <div className="flex justify-center items-center mt-2">
                                <ImSpinner9 className="animate-spin w-5 h-5" />
                            </div>
                        )}
                    </div>
                </div>

                <div className="font-bold text-gray-900 dark:text-gray-300 flex flex-col py-3 px-6 rounded-lg bg-white dark:bg-gray-850 shadow">
                    <div className="flex items-center justify-center text-sm">
                        <FaBuffer className="text-orange-300 w-6 h-6 flex-shrink-0 mr-1.5" />
                        <div>Size</div>
                    </div>
                    <div className="text-center text-lg mt-1.5">
                        {diskStats?.size ? (
                            diskStats.size
                        ) : (
                            <div className="flex justify-center items-center mt-2">
                                <ImSpinner9 className="animate-spin w-5 h-5" />
                            </div>
                        )}
                    </div>
                </div>

            </div>
        </div>

    );
}

export default DiskLive
