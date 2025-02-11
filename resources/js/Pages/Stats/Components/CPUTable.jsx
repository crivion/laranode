import React from "react";
import DataTable from "react-data-table-component";
import darkModeStyles from './TblDarkModeStyle'

export default function CPUTable({ cpuStats }) {

    // Define columns
    const columns = [
        {
            name: "TIME",
            selector: (row) => row.time,
            sortable: true,
            cell: (row) => (
                <span className="bg-gray-200 text-gray-700 px-2 py-1 rounded-lg">
                    {row.time}
                </span>
            ),
        },
        {
            name: "USER",
            selector: (row) => row.user,
            sortable: true,
            cell: (row) => <span>{row.user}%</span>,
        },
        {
            name: "SYSTEM",
            selector: (row) => row.system,
            sortable: true,
            cell: (row) => <span>{row.system}%</span>,
        },
        {
            name: "TOTAL",
            selector: (row) => row.total,
            sortable: true,
            cell: (row) => (
                <span className="bg-gray-200 text-gray-700 px-2 py-1 rounded-lg">
                    {row.total.toFixed(2)}%
                </span>
            ),
        },
    ];

    return (
        <DataTable
            columns={columns}
            data={cpuStats}
            pagination
            highlightOnHover
            customStyles={localStorage.getItem("theme") === "dark" ? darkModeStyles : {}}
        />
    );
}

