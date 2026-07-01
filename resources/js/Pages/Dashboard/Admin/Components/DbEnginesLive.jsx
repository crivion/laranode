import { FaArrowUpShortWide, FaMicrochip } from "react-icons/fa6";
import { TbBrandMysql, TbDatabase } from "react-icons/tb";
import { LuMemoryStick } from "react-icons/lu";

/**
 * Capitalise first letter of each word in the engine key.
 * mysql -> MySQL, mariadb -> MariaDB, postgres -> Postgres
 */
function formatLabel(engineKey) {
    const map = {
        mysql: 'MySQL',
        mariadb: 'MariaDB',
        postgres: 'Postgres',
    };
    return map[engineKey] ?? (engineKey.charAt(0).toUpperCase() + engineKey.slice(1));
}

function EngineIcon({ engineKey }) {
    if (engineKey === 'mysql' || engineKey === 'mariadb') {
        return <TbBrandMysql className="text-indigo-500 w-5 h-5 flex-shrink-0" />;
    }
    return <TbDatabase className="text-indigo-500 w-5 h-5 flex-shrink-0" />;
}

const DbEnginesLive = ({ dbEngines }) => {
    if (!dbEngines || Object.keys(dbEngines).length === 0) {
        return null;
    }

    return (
        <>
            {Object.entries(dbEngines).map(([engineKey, stats]) => (
                <div key={engineKey} className="mt-2">
                    <div className="flex items-center space-x-2">
                        <div>
                            <EngineIcon engineKey={engineKey} />
                        </div>
                        <div className="text-gray-600 dark:text-gray-400 text-lg">
                            {formatLabel(engineKey)}
                        </div>
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
            ))}
        </>
    );
};

export default DbEnginesLive;
