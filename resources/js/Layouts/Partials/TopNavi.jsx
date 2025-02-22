import { Link, usePage } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { useState, useEffect } from 'react';
import { MdOutlineLogout, MdOutlineLightMode, MdOutlineDarkMode } from "react-icons/md";
import { HiOutlineLogin } from "react-icons/hi";

const TopNavi = () => {

    const [theme, setTheme] = useState(localStorage.getItem("theme") || "light");
    const { auth } = usePage().props;

    const toggleTheme = () => {
        setTheme(theme === "light" ? "dark" : "light");
    };

    useEffect(() => {
        document.documentElement.classList.toggle("dark", theme === "dark");
        localStorage.setItem("theme", theme);
    }, [theme]);

    return (
        <nav className="fixed w-full flex items-center justify-between h-14 z-10 pb-2 bg-gray-950">
            <div className="flex items-center w-full h-14 px-1">
                <div className="flex h-14 justify-between">
                    <div className="flex">
                        <div className="flex shrink-0 items-center">
                            <Link href="/">
                                <ApplicationLogo className="block h-12 w-auto fill-current text-gray-800 dark:text-gray-200" logotextclass="" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
            <div className="flex items-center space-x-2 pr-3">
                <button
                    onClick={toggleTheme}
                    className="text-white font-bold"
                >
                    {theme === "light" ? <MdOutlineLightMode className='w-5 h-5' /> : <MdOutlineDarkMode className='w-6 h-6' />}
                </button>

                {auth.isImpersonating && (
                    <Link href={route('accounts.leaveImpersonation')} as="button" className='flex items-center space-x-2 text-white'>
                        <HiOutlineLogin className='w-5 h-5' />
                        Leave
                    </Link>
                )}

                {!auth.isImpersonating && (
                    <Link href={route('logout')} method="post" as="button" className='flex items-center space-x-2 text-white'>
                        <MdOutlineLogout className='w-5 h-5' />
                        Logout
                    </Link>
                )}
            </div>
        </nav>);
}

export default TopNavi
