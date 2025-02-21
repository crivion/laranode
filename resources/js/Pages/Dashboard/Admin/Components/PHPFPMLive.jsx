import { FaArrowUpShortWide, FaMicrochip } from "react-icons/fa6";
import { ImSpinner9 } from "react-icons/im";
import { TbBrandPhp } from "react-icons/tb";
import { LuMemoryStick } from "react-icons/lu";

const PHPFPMLive = ({ phpStats }) => {

    if (!phpStats) {
        return (
            <div className="mt-2">
                <div className="flex items-center space-x-2">
                    <div>
                        <TbBrandPhp className="text-blue-600 w-5 h-5 flex-shrink-0" />
                    </div>
                    <div className="text-gray-600 dark:text-gray-400 text-lg">PHP FPM</div>
                </div>

                <div className="mt-2 font-bold text-gray-900 dark:text-gray-300 py-3 px-6 rounded-lg bg-white dark:bg-gray-850 shadow flex items-center flex-wrap justify-between">
                    <div className="flex justify-center items-center mt-2">
                        <ImSpinner9 className="animate-spin w-5 h-5" />
                    </div>
                </div>
            </div>
        );
    }

    return (<>
        {phpStats && Object.entries(phpStats).map(([phpFpmVersion, stats]) => (
            <div className="mt-2" key={phpFpmVersion}>
                <div className="flex items-center space-x-2">
                    <div>
                        <TbBrandPhp className="text-blue-600 w-5 h-5 flex-shrink-0" />
                    </div>

                    <div className="text-gray-600 dark:text-gray-400 text-lg">{phpFpmVersion}</div>
                </div>

                <div className="mt-2 font-bold text-gray-900 dark:text-gray-300 grid grid-cols-1 space-y-2 xl:grid-cols-3 xl:space-x-4 xl:space-y-0">

                    <div className="text-sm bg-white dark:bg-gray-850 rounded-lg shadow py-3 px-6">
                        <div className="flex items-center justify-center">
                            <LuMemoryStick className="text-teal-500 w-6 h-6 flex-shrink-0 mr-1" />
                            <div>Memory</div>
                        </div>
                        <div className="text-lg mt-1.5 text-center">
                            {stats?.memory ? (
                                stats.memory
                            ) : (
                                <div className="flex justify-center items-center mt-2">
                                    <ImSpinner9 className="animate-spin w-5 h-5" />
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="text-sm bg-white dark:bg-gray-850 rounded-lg shadow py-3 px-6">
                        <div className="flex items-center justify-center">
                            <FaMicrochip className="text-indigo-500 w-6 h-6 flex-shrink-0 mr-1" />
                            <div>CPU Time</div>
                        </div>
                        <div className="text-sm mt-1.5 text-center">
                            {stats?.cpuTime && stats.cpuTime}
                        </div>
                    </div>

                    <div className="text-sm bg-white dark:bg-gray-850 rounded-lg shadow py-3 px-6">
                        <div className="flex items-center justify-center">
                            <FaArrowUpShortWide className="text-lime-500 dark:text-lime-200 w-6 h-6 flex-shrink-0 mr-1.5" />
                            <div>Uptime</div>
                        </div>
                        <div className="text-sm mt-1.5 text-center">
                            {stats?.uptime && stats.uptime}
                        </div>
                    </div>
                </div>
            </div>
        ))
        }
    </>
    );
}

export default PHPFPMLive;
