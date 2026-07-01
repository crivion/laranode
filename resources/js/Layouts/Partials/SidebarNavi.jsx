import { Link, usePage } from "@inertiajs/react";
import { RiDashboard3Fill, RiMvFill } from "react-icons/ri";
import { ImProfile } from "react-icons/im";
import { FaPhp, FaUsers } from "react-icons/fa6";
import { VscFileSubmodule } from "react-icons/vsc";
import { TbDatabase, TbChartBar, TbWorldWww } from "react-icons/tb";
import { MdSecurity, MdOutlineListAlt, MdSchedule, MdBackup } from "react-icons/md";
import { IoLockClosedOutline } from "react-icons/io5";

const SidebarNavi = ({ isCollapsed, setIsCollapsed }) => {

    const { auth } = usePage().props;

    const labelClass = `ml-2 text-sm tracking-wide truncate${isCollapsed ? ' hidden' : ''}`;

    return (<div className={`fixed flex flex-col top-14 left-0 ${isCollapsed ? 'w-14' : 'w-64'} bg-gray-950 dark:bg-gray-900 h-full text-white transition-all duration-300 border-none z-10 sidebar`}>
        <div className="overflow-y-auto overflow-x-hidden flex flex-col justify-between flex-grow dark:border-gray-800 dark:border-r">
            <ul className="flex flex-col py-4 space-y-2">
                <li>
                    <div className="flex flex-row items-center h-8">
                        <div className={`text-sm font-light tracking-wide text-gray-400 uppercase ml-4 ${isCollapsed ? 'hidden' : 'hidden md:block'}`}>
                            Menu
                        </div>
                        <div>
                            <button onClick={() => {
                                const next = !isCollapsed;
                                setIsCollapsed(next);
                                localStorage.setItem('laranode_sidebar_collapsed', String(next));
                            }} className="text-gray-400 ml-4">
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
                        <span className={labelClass}>Dashboard</span>
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
                            <span className={labelClass}>Accounts</span>
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
                        <span className={labelClass}>Websites</span>
                    </Link>
                </li>

                {auth.user.role == 'admin' && (
                    <li>
                        <Link
                            href={route('firewall.index')}
                            className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                        >
                            <div>
                                <MdSecurity className="ml-3 w-5 h-5" />
                            </div>
                            <span className={labelClass}>Firewall</span>
                        </Link>
                    </li>
                )}

                {auth.user.role == 'admin' && (
                    <li>
                        <Link
                            href={route('operations.index')}
                            className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                        >
                            <div>
                                <MdOutlineListAlt className="ml-3 w-5 h-5" />
                            </div>
                            <span className={labelClass}>Operations</span>
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
                        <span className={labelClass}>File Manager</span>
                    </Link>
                </li>

                <li>
                    <Link
                        href={route('analytics.index')}
                        className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                    >
                        <div>
                            <TbChartBar className="ml-3 w-5 h-5" />
                        </div>
                        <span className={labelClass}>Analytics</span>
                    </Link>
                </li>

                <li>
                    <Link
                        href={route('databases.index')}
                        className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                    >
                        <div>
                            <TbDatabase className="ml-3 w-5 h-5" />
                        </div>
                        <span className={labelClass}>Databases</span>
                    </Link>
                </li>

                <li>
                    <Link
                        href={route('cron-jobs.index')}
                        className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                    >
                        <div>
                            <MdSchedule className="ml-3 w-5 h-5" />
                        </div>
                        <span className={labelClass}>Cron Jobs</span>
                    </Link>
                </li>

                {auth.user.role == 'admin' && (
                    <li>
                        <Link
                            href={route('php.index')}
                            className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                        >
                            <div>
                                <FaPhp className="ml-3 w-5 h-5" />
                            </div>
                            <span className={labelClass}>PHP Manager</span>
                        </Link>
                    </li>
                )}

                <li>
                    <Link
                        href={route('backups.index')}
                        className="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-900 text-gray-300 border-l-4 border-transparent hover:border-indigo-900 pr-6"
                    >
                        <div>
                            <MdBackup className="ml-3 w-5 h-5" />
                        </div>
                        <span className={labelClass}>Backups</span>
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
                        <span className={labelClass}>My Profile</span>
                    </Link>
                </li>
            </ul>

            <p className={`mb-14 px-5 py-3 text-center text-xs border-t border-gray-800 ${isCollapsed ? 'hidden' : 'hidden md:block'}`}>
                <span className="font-semibold text-white block">LaraNode</span>
                <span className="text-gray-300">Hosting Control Panel</span>
            </p>
        </div>
    </div>);
}

export default SidebarNavi
