import { Link } from '@inertiajs/react';

import { FaMemory, FaBuffer, } from "react-icons/fa6";
import { FaAngleDoubleRight } from "react-icons/fa";
import { ImSpinner9 } from "react-icons/im";
import { GiPenguin, GiProgression } from "react-icons/gi";
import { MdOutlineSummarize } from "react-icons/md";

const MemoryLive = ({ memoryStats }) => {

    return (
        <div className="bg-white dark:bg-gray-850 p-6 rounded-lg shadow-md">

            <div class="flex items-center space-x-2">
                <div>
                    <FaMemory className="text-teal-500 w-5 h-5 flex-shrink-0" />
                </div>

                <div className="text-gray-600 dark:text-gray-400 text-lg flex-grow">Memory Usage</div>

                <div className="justify-self-end">
                    <Link href="/stats/history">
                        <FaAngleDoubleRight className="text-gray-300 text-lg" />
                    </Link>
                </div>
            </div>

            <div className="flex flex-col space-y-1 mt-3">
                <p className=" font-bold text-gray-900 dark:text-gray-300 flex items-center">
                    <GiProgression className="text-lime-500 dark:text-lime-200 text-3xl w-3 h-3 flex-shrink-0 mr-1" />
                    Used: {memoryStats?.used ? (
                        memoryStats.used + "MB"
                    ) : (
                        <ImSpinner9 className="ml-1 animate-spin w-3 h-3" />
                    )}
                </p>
                <p className=" font-bold text-gray-900 dark:text-gray-300 flex items-center">
                    <GiPenguin className="text-indigo-500 text-3xl w-3 h-3 flex-shrink-0 mr-1" />
                    Free: {memoryStats?.free ? (
                        memoryStats.free + "MB"
                    ) : (
                        <ImSpinner9 className="ml-1 animate-spin w-3 h-3" />
                    )}
                </p>
                <p className=" font-bold text-gray-900 dark:text-gray-300 flex items-center">
                    <FaBuffer className="text-orange-300 text-3xl w-3 h-3 flex-shrink-0 mr-1" />
                    Buff/Cache: {memoryStats?.buffcache ? (
                        memoryStats.buffcache + "MB"
                    ) : (
                        <ImSpinner9 className="ml-1 animate-spin w-3 h-3" />
                    )}
                </p>
                <p className=" font-bold text-gray-900 dark:text-gray-300 flex items-center">
                    <MdOutlineSummarize className="text-pink-400 text-3xl w-3 h-3 flex-shrink-0 mr-1" />
                    Total: {memoryStats?.total ? (
                        memoryStats.total + "MB"
                    ) : (
                        <ImSpinner9 className="ml-1 animate-spin w-3 h-3" />
                    )}
                </p>
            </div>
        </div>
    );
}

export default MemoryLive
