import { LuArrowDownFromLine } from "react-icons/lu";
import { LuArrowUpFromLine } from "react-icons/lu";

const NetworkLive = ({ networkStats }) => {

    return (
        <div className="flex flex-col xl:flex-row xl:space-x-2 space-y-2 xl:space-y-0 text-xs font-light mt-3 xl:mt-0">
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
    );
}

export default NetworkLive
