
const StatCard = ({ icon, label, value, status, subtext }) => {
    return (
        <div className="bg-white dark:bg-gray-850 p-6 rounded-lg shadow-md">
            <div className="flex items-center space-x-3">
                {icon}
                <div>
                    <p className="text-gray-600 dark:text-gray-400">{label}</p>
                    <p className="text-xl font-bold dark:text-gray-300">{value}</p>
                    {status && <p className="text-sm flex items-center mt-0.5">Status: <span className="ml-1 dark:text-gray-300">{status}</span></p>}
                    {subtext && <p className="text-sm text-gray-500 dark:text-gray-400">{subtext}</p>}
                </div>
            </div>
        </div>
    );
}

export default StatCard
