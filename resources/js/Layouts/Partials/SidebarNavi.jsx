import { Link, usePage } from "@inertiajs/react";
import { useState } from "react";
import { RiDashboard3Fill, RiMvFill } from "react-icons/ri";
import { ImProfile } from "react-icons/im";
import { FaPhp, FaUsers } from "react-icons/fa6";
import { VscFileSubmodule } from "react-icons/vsc";
import { TbBrandMysql } from "react-icons/tb";
import { MdSecurity } from "react-icons/md";
import { IoLockClosedOutline } from "react-icons/io5";
import { TbWorldWww } from "react-icons/tb";

const SidebarNavi = () => {

    const { auth } = usePage().props;
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);

    return (<div className={`fixed flex flex-col top-14 left-0 ${isSidebarOpen ? 'w-64' : 'w-14'} md:w-64 bg-gray-950 dark:bg-gray-900 h-full text-white transition-all duration-300 border-none z-10 sidebar`}>
        <div className="overflow-y-auto overflow-x-hidden flex flex-col justify-between flex-grow dark:border-gray-800 dark:border-r">
            <ul className="flex flex-col py-4 space-y-2">
                <li>
                    <div className="flex flex-row items-center h-8">
                        <div className="text-sm font-light tracking-wide text-gray-400 uppercase ml-4 hidden md:block">
                            Menu
                        </div>
                        <div>
                            <button onClick={() => setIsSidebarOpen(!isSidebarOpen)} className="text-gray-400 ml-4 block md:hidden">
                                <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fillRule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clipRule="evenodd"></path></svg>
                            </button>
                        </div>
                    </div>
                </li>

                <li>

                    <Link
                        href={route('dashboard')}
                        className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                    >
                        <div>
                            <RiDashboard3Fill className="ml-3 w-5 h-5" />
                        </div>
                        <span className="ml-2 text-sm tracking-wide truncate">Dashboard</span>
                    </Link>
                </li>

                {auth.user.role == 'admin' && (
                    <li>
                        <Link
                            href={route('accounts.index')}
                            className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                        >
                            <div>
                                <FaUsers className="ml-3 w-5 h-5" />
                            </div>
                            <span className="ml-2 text-sm tracking-wide truncate">Accounts</span>
                        </Link>
                    </li>
                )}

                <li>
                    <Link
                        href={route('websites.index')}
                        className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                    >
                        <div>
                            <TbWorldWww className="ml-3 w-5 h-5" />
                        </div>
                        <span className="ml-2 text-sm tracking-wide truncate">Websites</span>
                    </Link>
                </li>

                {auth.user.role == 'admin' && (
                    <li>
                        <Link
                            href="/admin/firewall"
                            className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                        >
                            <div>
                                <MdSecurity className="ml-3 w-5 h-5" />
                            </div>
                            <span className="ml-2 text-sm tracking-wide truncate">Firewall</span>
                        </Link>
                    </li>
                )}

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
                        href="/mysql"
                        className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                    >
                        <div>
                            <TbBrandMysql className="ml-3 w-5 h-5" />
                        </div>
                        <span className="ml-2 text-sm tracking-wide truncate">MySQL DBs</span>
                    </Link>
                </li>

                {auth.user.role == 'admin' && (
                    <li>
                        <Link
                            href="/php-manager"
                            className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                        >
                            <div>
                                <FaPhp className="ml-3 w-5 h-5" />
                            </div>
                            <span className="ml-2 text-sm tracking-wide truncate">PHP Manager</span>
                        </Link>
                    </li>
                )}

                <li>
                    <Link
                        href="/ssl"
                        className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                    >
                        <div>
                            <IoLockClosedOutline className="ml-3 w-5 h-5" />
                        </div>
                        <span className="ml-2 text-sm tracking-wide truncate">SSL Manager</span>
                    </Link>
                </li>

                <li>
                    <Link
                        href={route('profile.edit')}
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
