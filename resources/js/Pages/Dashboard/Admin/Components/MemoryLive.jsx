import { Link } from '@inertiajs/react';

import { FaMemory, FaBuffer, } from "react-icons/fa6";
import { FaAngleDoubleRight } from "react-icons/fa";
import { ImSpinner9 } from "react-icons/im";
import { GiPenguin, GiProgression } from "react-icons/gi";
import { MdOutlineSummarize } from "react-icons/md";

const MemoryLive = ({ memoryStats }) => {

    return (
        <div className="mt-5">
            <div className="flex items-center space-x-2">
                <div>
                    <FaMemory className="text-teal-500 w-5 h-5 flex-shrink-0" />
                </div>
                <div className="text-gray-600 dark:text-gray-400 text-lg">Memory Usage</div>
                <div className="ml-2">
                    <Link href="/stats/history">
                        <FaAngleDoubleRight className="text-gray-300 text-lg" />
                    </Link>
                </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mt-3">
                <div className="font-bold text-gray-900 dark:text-gray-300 flex flex-col py-3 px-6 rounded-lg bg-white dark:bg-gray-850 shadow">
                    <div className="flex items-center justify-center text-sm">
                        <GiProgression className="text-lime-500 dark:text-lime-200 w-6 h-6 flex-shrink-0 mr-1" />
                        <div>Used</div>
                    </div>
                    <div className="text-center text-2xl mt-1.5">
                        {memoryStats?.used ? (
                            memoryStats.used + "MB"
                        ) : (
                            <div className="flex justify-center items-center mt-2">
                                <ImSpinner9 className="animate-spin w-5 h-5" />
                            </div>
                        )}
                    </div>
                </div>

                <div className="font-bold text-gray-900 dark:text-gray-300 flex flex-col py-3 px-6 rounded-lg bg-white dark:bg-gray-850 shadow">
                    <div className="flex items-center justify-center text-sm">
                        <GiPenguin className="text-indigo-500 w-6 h-6 flex-shrink-0" />
                        <div>Free</div>
                    </div>
                    <div className="text-center text-lg mt-1.5">
                        {memoryStats?.free ? (
                            memoryStats.free + "MB"
                        ) : (
                            <div className="flex justify-center items-center mt-2">
                                <ImSpinner9 className="animate-spin w-5 h-5" />
                            </div>
                        )}
                    </div>
                </div>

                <div className="font-bold text-gray-900 dark:text-gray-300 flex flex-col py-3 px-6 rounded-lg bg-white dark:bg-gray-850 shadow">
                    <div className="flex items-center justify-center text-sm">
                        <FaBuffer className="text-orange-300 w-6 h-6 flex-shrink-0 mr-1" />
                        <div>Buff/Cache</div>
                    </div>
                    <div className="text-center text-lg mt-1.5">
                        {memoryStats?.buffcache ? (
                            memoryStats.buffcache + "MB"
                        ) : (
                            <div className="flex justify-center items-center mt-2">
                                <ImSpinner9 className="animate-spin w-5 h-5" />
                            </div>
                        )}
                    </div>
                </div>

                <div className="font-bold text-gray-900 dark:text-gray-300 flex flex-col py-3 px-6 rounded-lg bg-white dark:bg-gray-850 shadow">
                    <div className="flex items-center justify-center text-sm">
                        <MdOutlineSummarize className="text-pink-400 w-6 h-6 flex-shrink-0" />
                        <div>Total</div>
                    </div>
                    <div className="text-center text-lg mt-1.5">
                        {memoryStats?.total ? (
                            memoryStats.total + "MB"
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

export default MemoryLive
