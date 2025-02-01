import { LuArrowDownFromLine } from "react-icons/lu";
import { LuArrowUpFromLine } from "react-icons/lu";
import { BsHddNetwork } from "react-icons/bs";
import { ImSpinner9 } from "react-icons/im";

const NetworkLive = ({ networkStats }) => {

    return (
        <div className="flex flex-col xl:flex-row xl:space-x-2 space-y-2 xl:space-y-0 text-xs font-light mt-3 xl:mt-0">
            <div className="xl:hidden flex items-center space-x-2">
                <div>
                    <BsHddNetwork className="text-green-400 w-5 h-5 flex-shrink-0" />
                </div>
                <div className="text-gray-600 dark:text-gray-400 text-lg">Network Traffic <span class="text-xs">since boot</span></div>
            </div>
            <div className="flex flex-col xl:flex-row xl:space-x-2 space-y-2 xl:space-y-0 xl:bg-transparent xl:dark:bg-transparent xl:py-0 xl:px-0 bg-white dark:bg-gray-850 shadow rounded-lg py-3 px-6 xl:shadow-none">
                {!networkStats?.length && (
                    <center>
                        <ImSpinner9 className="animate-spin w-5 h-5" />
                    </center>
                )}
                {networkStats?.map((dev, index) => (
                    <div key={`netdev-${index}`} className="flex space-x-2 items-center">
                        <div className="bg-gray-200 dark:bg-gray-700 rounded-full text-xs font-semibold text-gray-800 dark:text-gray-200 py-1 px-2 uppercase">
                            {dev?.interface}
                        </div>
                        <div className="text-gray-700 dark:text-gray-300 flex items-center space-x-1">
                            <LuArrowDownFromLine className="text-green-500 w-3 h-3 flex-shrink-0" />
                            {dev?.rx} GB
                        </div>
                        <div className="text-gray-700 dark:text-gray-300 flex items-center space-x-1">
                            <LuArrowUpFromLine className="text-sky-500 w-3 h-3 flex-shrink-0" />
                            {dev?.tx} GB
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default NetworkLive
