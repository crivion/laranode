import { Link } from '@inertiajs/react';
import { FaMicrochip, FaBarsProgress, FaArrowUpShortWide, FaSitemap } from "react-icons/fa6";
import { FaAngleDoubleRight } from "react-icons/fa";
import { TbAntennaBars5 } from "react-icons/tb";
import { ImSpinner9 } from "react-icons/im";

const CPULive = ({ cpuStats }) => {

    return (
        <div className="bg-white dark:bg-gray-850 p-6 rounded-lg shadow-md">

            <div class="flex items-center space-x-2">
                <div>
                    <FaMicrochip className="text-indigo-500 w-5 h-5 flex-shrink-0" />
                </div>

                <div className="text-gray-600 dark:text-gray-400 text-lg flex-grow">CPU Stats</div>

                <div className="justify-self-end">
                    <Link href={"/cpu/history"}>
                        <FaAngleDoubleRight className="text-gray-300 text-lg" />
                    </Link>
                </div>
            </div>

            <div className="flex flex-col space-y-1 mt-3">
                <p className=" font-bold text-gray-900 dark:text-gray-300 flex items-center">
                    <TbAntennaBars5 className="text-indigo-500 text-3xl w-3 h-3 flex-shrink-0 mr-1" />
                    Live Load: {cpuStats?.usage ? (
                        cpuStats.usage + "%"
                    ) : (
                        <ImSpinner9 className="ml-1 animate-spin w-3 h-3" />
                    )}
                </p>
                <p className=" font-bold text-gray-900 dark:text-gray-300 flex items-center">
                    <FaBarsProgress className="text-orange-300 text-3xl w-3 h-3 flex-shrink-0 mr-1" />
                    Average: {cpuStats?.loadTimes ? (
                        cpuStats.loadTimes
                    ) : (
                        <ImSpinner9 className="ml-1 animate-spin w-3 h-3" />
                    )}
                </p>
                <p className=" font-bold text-gray-900 dark:text-gray-300 flex items-center">
                    <FaSitemap className="text-pink-400 text-3xl w-3 h-3 flex-shrink-0 mr-1" />
                    Processes: {cpuStats?.processCount ? (
                        cpuStats.processCount
                    ) : (
                        <ImSpinner9 className="ml-1 animate-spin w-3 h-3" />
                    )}
                </p>
                <p className=" font-bold text-gray-900 dark:text-gray-300 flex items-center">
                    <FaArrowUpShortWide className="text-lime-500 dark:text-lime-200 text-3xl w-3 h-3 flex-shrink-0 mr-1" />
                    Uptime: {cpuStats?.uptime ? (
                        cpuStats.uptime
                    ) : (
                        <ImSpinner9 className="ml-1 animate-spin w-3 h-3" />
                    )}
                </p>

            </div>
        </div>
    );
}

export default CPULive
