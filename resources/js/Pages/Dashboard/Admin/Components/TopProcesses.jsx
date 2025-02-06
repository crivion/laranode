import { Tooltip } from 'react-tooltip'
import { useEffect, useState } from "react";
import { FaSitemap, FaArrowDown91 } from 'react-icons/fa6';
import { ImSpinner9 } from "react-icons/im";


const TopProcesses = () => {
    const [topStats, setTopStats] = useState([]);
    const [sortBy, setSortBy] = useState("cpu");
    const [spinner, showSpinner] = useState(false);

    const echo = window.Echo;

    const setSortPreferrence = (sortBy) => {
        window.axios.patch("/dashboard/admin/set/top-sort", { sortBy }).then((response) => {
            setSortBy(response.data.sortBy);
            showSpinner(true);
        });
    }

    useEffect(() => {

        const topStatsChannel = echo.private("topstats");

        window.axios.get("/dashboard/admin/get/top-sort").then((response) => {
            setSortBy(response.data.sortBy);
        });

        topStatsChannel.listen("TopStatsEvent", (data) => {
            setTopStats(data);
            showSpinner(false);
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

    return (<>
        <div className="flex items-center justify-between flex-wrap mt-3">
            <h3 className="text-lg font-semibold text-gray-600 dark:text-gray-400 flex items-center">
                <FaSitemap className="text-pink-400 w-6 h-6 flex-shrink-0 mr-1" />
                Top 20 Processes
            </h3>
            <div className="inline-flex">
                {spinner ? <ImSpinner9 className="animate-spin w-4 h-4 mr-1" /> :
                    (<>
                        <button className={`flex items-center ${sortBy === "memory" ? "text-indigo-500 dark:text-indigo-400" : "text-gray-600 dark:text-gray-400"}`} onClick={() => setSortPreferrence("memory")}>
                            <FaArrowDown91 className='mr-1.5' />
                            Memory
                        </button>
                        <button className={`ml-1.5 flex items-center ${sortBy === "cpu" ? "text-indigo-500 dark:text-indigo-400" : "text-gray-600 dark:text-gray-400"}`} onClick={() => setSortPreferrence("cpu")}>
                            <FaArrowDown91 className='mr-1.5' />
                            CPU
                        </button>
                    </>)}
            </div>
        </div>
        <div className="relative overflow-x-auto pb-12 bg-white dark:bg-gray-850 mt-3">
            <table className="w-full  text-left rtl:text-right text-gray-500 dark:text-gray-400">
                <thead className="text-gray-700 uppercase bg-gray-200 dark:bg-gray-700 dark:text-gray-300 text-sm">
                    <tr>
                        <th className="px-6 py-3">PID</th>
                        <th className="px-6 py-3">%CPU</th>
                        <th className="px-6 py-3">%MEM</th>
                        <th className="px-6 py-3">USER</th>
                        <th className="px-6 py-3">COMMAND</th>
                    </tr>
                </thead>
                <tbody className="text-sm">
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
        </div>
    </>);
}

export default TopProcesses
