const darkModeStyles = {
    table: {
        style: {
            backgroundColor: "#172132", // Dark mode background
            borderRadius: 0,
        },
    },
    headRow: {
        style: {
            backgroundColor: "#1d293b", // Slightly lighter for header
            color: "#fff",
        },
    },
    rows: {
        style: {
            backgroundColor: "#172132", // Match dark background
            color: "#d1d5db", // Tailwind gray-300 for text
        },
        highlightOnHoverStyle: {
            backgroundColor: "#1e2a3d", // Slightly lighter on hover
            color: "#ffffff",
            transition: "background-color 0.15s ease-in-out",
        },
    },
    pagination: {
        style: {
            backgroundColor: '#172132', // Dark background
            color: '#ffffff', // White text
            borderTop: '1px solid #2a3b4d', // Optional border
        },
        pageButtonsStyle: {
            color: '#ffffff', // Page button color
            fill: '#ffffff', // Icon color
            backgroundColor: '#1e293b', // Button background
            borderRadius: '6px',
            padding: '6px',
            margin: '0 4px',
            '&:hover': {
                backgroundColor: '#334155', // Hover effect
            },
            '&:disabled': {
                backgroundColor: '#1e293b',
            },
        }
    }
};

export default darkModeStyles
