import { FaArrowUpShortWide, FaMicrochip } from "react-icons/fa6";
import { ImSpinner9 } from "react-icons/im";
import { TbBrandPhp } from "react-icons/tb";
import { LuMemoryStick } from "react-icons/lu";

const PHPFPMLive = ({ phpStats }) => {

    if (!phpStats) return;

    return (<>
        {phpStats && Object.entries(phpStats).map(([phpFpmVersion, stats]) => (
            <div className="mt-2" key={phpFpmVersion}>
                <div className="flex items-center space-x-2">
                    <div>
                        <TbBrandPhp className="text-blue-600 w-5 h-5 flex-shrink-0" />
                    </div>
                    <div className="text-gray-600 dark:text-gray-400 text-lg">{phpFpmVersion}</div>
                </div>

                <div className="mt-2.5 flex flex-col justify-center space-y-2 text-sm bg-white dark:bg-gray-850 text-gray-900 dark:text-gray-300 rounded-lg shadow py-3 px-6">
                    <div className="flex items-center">
                        <LuMemoryStick className="text-teal-500 w-3 h-3 flex-shrink-0 mr-1" />
                        {stats?.memory ? stats.memory : '--'}
                    </div>
                    <div className="flex items-center">
                        <FaMicrochip className="text-indigo-500 w-3 h-3 flex-shrink-0 mr-1" />
                        {stats?.cpuTime ? stats.cpuTime : '--'}
                    </div>
                    <div className="flex items-center">
                        <FaArrowUpShortWide className="text-lime-500 dark:text-lime-200 w-3 h-3 flex-shrink-0 mr-1" />
                        {stats?.uptime ? stats.uptime : '--'}
                    </div>
                </div>

            </div>
        ))
        }
    </>
    );
}

export default PHPFPMLive;
