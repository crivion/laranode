import { Tooltip } from 'react-tooltip'
import { useEffect, useState } from "react";


const TopProcesses = () => {
    const [topStats, setTopStats] = useState([]);

    const echo = window.Echo;
    const topStatsChannel = echo.private("topstats");

    useEffect(() => {

        topStatsChannel.listen("TopStatsEvent", (data) => {
            setTopStats(data);
        });

        // Set interval to "whisper" every 2 seconds
        // Makes it so we get stats via sockets
        const whisperInterval = setInterval(() => {
            topStatsChannel.whisper("typing", { requesting: "dashboard-top-stats" });
        }, 2000);

        return () => {
            clearInterval(whisperInterval);
            echo.leave("topstats");
        };
    }, []);


    { topStats?.error && <ShowError error={topStats?.error} /> }

    return (
        <div className="relative overflow-x-auto pt-2 pb-12">
            <div className="flex items-center justify-between flex-wrap">
                <h3 className="text-lg font-semibold text-gray-600 dark:text-gray-400">Top 20 Processes</h3>
                <div>
                    TOGGLE CPu | MEm
                </div>
            </div>
            <table className="w-full  text-left rtl:text-right text-gray-500 dark:text-gray-400 mt-5">
                <thead className="text-gray-700 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-300">
                    <tr>
                        <th className="px-6 py-3">PID</th>
                        <th className="px-6 py-3">%CPU</th>
                        <th className="px-6 py-3">%MEM</th>
                        <th className="px-6 py-3">USER</th>
                        <th className="px-6 py-3">COMMAND</th>
                    </tr>
                </thead>
                <tbody className="">
                    {topStats?.length > 0 ? (
                        topStats?.map((process, index) => (
                            <tr key={`proc-${index}`} className="bg-white border-b text-gray-700 dark:text-gray-200 dark:bg-gray-850 dark:border-gray-700 border-gray-200">
                                <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                    {process.pid}
                                </td>
                                <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                    {process.cpu}%
                                </td>
                                <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                    {process.mem}%
                                </td>
                                <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                    {process.user}
                                </td>
                                <td className="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                    <button
                                        type="button"
                                        data-tooltip-id={`tooltip-${process.pid}`}
                                        data-tooltip-content={process.restOfCmd?.join(" ") || "No extra arguments"}
                                        data-tooltip-place="top"
                                        title={process.restOfCmd?.join(" ") || ""} // Tooltip equivalent
                                    >
                                        {process.mainCmd}
                                    </button>
                                    <Tooltip id={`tooltip-${process.pid}`} />
                                </td>
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td colSpan="5" className="px-6 py-4 text-center text-gray-500">
                                No processes found.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>);
}

export default TopProcesses
