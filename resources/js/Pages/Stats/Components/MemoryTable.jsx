import DataTable from "react-data-table-component";
import darkModeStyles from './TblDarkModeStyle'

export default function MemoryTable({ memoryStats }) {

    const columns = [
        {
            name: "TIME",
            selector: row => row.time,
            sortable: true,
            cell: row => (
                <span className="bg-gray-200 text-gray-700 px-2 py-1 rounded-lg">
                    {row.time}
                </span>
            ),
        },
        {
            name: "AVAIL",
            selector: row => row.avail,
            sortable: true,
            cell: row => `${row.avail.toFixed(2)}GB`,
        },
        {
            name: "USED",
            selector: row => row.used,
            sortable: true,
            cell: row => `${row.used.toFixed(2)}GB`,
        },
        {
            name: "PERCENT",
            selector: row => row.percent,
            sortable: true,
            cell: row => (
                <span className="bg-gray-200 text-gray-700 px-2 py-1 rounded-lg">
                    {row.percent.toFixed(2)}%
                </span>
            ),
        },
    ];

    return (
        <div className="relative overflow-x-auto mt-5">
            <DataTable
                columns={columns}
                data={memoryStats.slice(1)}
                pagination
                customStyles={localStorage.getItem("theme") === "dark" ? darkModeStyles : {}}
                highlightOnHover
            />
        </div>
    );
}

