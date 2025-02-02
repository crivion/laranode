import { Link } from "@inertiajs/react";
import { RiDashboard3Fill } from "react-icons/ri";
import { ImProfile } from "react-icons/im";
import { FaUsers } from "react-icons/fa6";
import { VscFileSubmodule } from "react-icons/vsc";

const SidebarNavi = () => {
    return (<div className="fixed flex flex-col top-14 left-0 w-14 hover:w-64 md:w-64 bg-gray-950 dark:bg-gray-900 h-full text-white transition-all duration-300 border-none z-10 sidebar">
        <div className="overflow-y-auto overflow-x-hidden flex flex-col justify-between flex-grow dark:border-gray-800 dark:border-r">
            <ul className="flex flex-col py-4 space-y-2">
                <li className="hidden md:block">
                    <div className="flex flex-row items-center h-8">
                        <div className="text-sm font-light tracking-wide text-gray-400 uppercase ml-4">
                            Menu
                        </div>
                    </div>
                </li>

                <li>

                    <Link
                        href="/dashboard"
                        className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                    >
                        <div>
                            <RiDashboard3Fill className="ml-3 w-5 h-5" />
                        </div>
                        <span className="ml-2 text-sm tracking-wide truncate">Dashboard</span>
                    </Link>
                </li>

                <li>
                    <Link
                        href="/filemanager"
                        className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                    >
                        <div>
                            <VscFileSubmodule className="ml-3 w-5 h-5" />
                        </div>
                        <span className="ml-2 text-sm tracking-wide truncate">File Manager</span>
                    </Link>
                </li>

                <li>
                    <Link
                        to="/admin/accounts"
                        className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                    >
                        <div>
                            <FaUsers className="ml-3 w-5 h-5" />
                        </div>
                        <span className="ml-2 text-sm tracking-wide truncate">Accounts</span>
                    </Link>
                </li>

                <li>
                    <Link
                        href="/profile"
                        className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                    >
                        <div>
                            <ImProfile className="ml-3 w-5 h-5" />
                        </div>
                        <span className="ml-2 text-sm tracking-wide truncate">My Profile</span>
                    </Link>
                </li>
            </ul>

            <p className="mb-14 px-5 py-3 hidden md:block text-center text-xs border-t border-gray-800">
                <span className="font-semibold text-white block">LaraNode</span>
                <span className="text-gray-300">Hosting Control Panel</span>
            </p>
        </div>
    </div>);
}

export default SidebarNavi
