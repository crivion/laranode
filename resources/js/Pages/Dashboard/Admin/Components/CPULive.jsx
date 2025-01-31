import { Link } from '@inertiajs/react';
import { FaMicrochip, FaBarsProgress, FaArrowUpShortWide, FaSitemap } from "react-icons/fa6";
import { FaAngleDoubleRight } from "react-icons/fa";
import { TbAntennaBars5 } from "react-icons/tb";
import { ImSpinner9 } from "react-icons/im";

const CPULive = ({ cpuStats }) => {

    return (
        <div className="">

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

            <div className="flex flex-col lg:flex-row space-y-1 mt-3 lg:space-x-5 lg:items-center">
                <div className="font-bold text-gray-900 dark:text-gray-300 flex flex-col py-3 px-6 rounded-lg bg-white dark:bg-gray-850 shadow">
                    <div className="flex items-center text-sm">
                        <div>
                            <TbAntennaBars5 className="text-indigo-500 w-6 h-6 flex-shrink-0" />
                        </div>
                        <div>
                            Live Load
                        </div>
                    </div>
                    <div className="text-center text-2xl">
                        {cpuStats?.usage ? (
                            cpuStats.usage + "%"
                        ) : (
                            <div className="flex justify-center items-center mt-2">
                                <ImSpinner9 className="animate-spin w-5 h-5" />
                            </div>
                        )}
                    </div>
                </div>

                <div className="font-bold text-gray-900 dark:text-gray-300 flex flex-col py-3 px-6 rounded-lg bg-white dark:bg-gray-850 shadow">
                    <div className="flex items-center text-sm">
                        <FaBarsProgress className="text-orange-300 w-6 h-6 flex-shrink-0 mr-1" />
                        <div>Average Load</div>
                    </div>
                    <div className="text-center text-2xl">
                        {cpuStats?.loadTimes ? (
                            cpuStats.loadTimes
                        ) : (
                            <div className="flex justify-center items-center mt-2">
                                <ImSpinner9 className="animate-spin w-5 h-5" />
                            </div>
                        )}
                    </div>
                </div>

                <div className="font-bold text-gray-900 dark:text-gray-300 flex flex-col py-3 px-6 rounded-lg bg-white dark:bg-gray-850 shadow">
                    <div className="flex items-center text-sm">
                        <FaSitemap className="text-pink-400 w-6 h-6 flex-shrink-0 mr-1" />
                        <div>Processes</div>
                    </div>
                    <div className="text-center text-2xl">
                        {cpuStats?.processCount ? (
                            cpuStats.processCount
                        ) : (
                            <div className="flex justify-center items-center mt-2">
                                <ImSpinner9 className="animate-spin w-5 h-5" />
                            </div>
                        )}
                    </div>
                </div>

                <div className="font-bold text-gray-900 dark:text-gray-300 flex flex-col py-3 px-6 rounded-lg bg-white dark:bg-gray-850 shadow">
                    <div className="flex items-center text-sm">
                        <FaArrowUpShortWide className="text-lime-500 dark:text-lime-200 w-6 h-6 flex-shrink-0 mr-0.5" />
                        <div>Uptime</div>
                    </div>
                    <div className="text-center text-2xl">
                        {cpuStats?.uptime ? (
                            cpuStats.uptime
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

export default CPULive
