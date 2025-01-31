export default function ApplicationLogo(props) {
    return (
        <div className="flex items-center">
            <div className="flex-shrink-0">
                <svg {...props} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">
                    {/* Server Rack Base */}
                    <rect x="50" y="60" width="100" height="120" fill="none" stroke="#45556C" strokeWidth="5" rx="10" />

                    {/* Server Shelves */}
                    <rect x="60" y="70" width="80" height="20" fill="#45556C" rx="5" />
                    <rect x="60" y="100" width="80" height="20" fill="#45556C" rx="5" />
                    <rect x="60" y="130" width="80" height="20" fill="#45556C" rx="5" />

                    {/* Fan Grilles */}
                    <circle cx="80" cy="80" r="5" fill="#D1D5DB" />
                    <circle cx="120" cy="80" r="5" fill="#D1D5DB" />
                    <circle cx="80" cy="110" r="5" fill="#D1D5DB" />
                    <circle cx="120" cy="110" r="5" fill="#D1D5DB" />
                    <circle cx="80" cy="140" r="5" fill="#D1D5DB" />
                    <circle cx="120" cy="140" r="5" fill="#D1D5DB" />

                    {/* Server LEDs */}
                    <circle cx="70" cy="80" r="3" fill="#10B981" /> {/* Green */}
                    <circle cx="70" cy="110" r="3" fill="#EF4444" /> {/* Red */}
                    <circle cx="70" cy="140" r="3" fill="#3B82F6" /> {/* Blue */}

                    {/* Continuous Cable Connections in the Middle (Lighter Gray) */}
                    <path d="M100,75 Q100,85 100,95 T100,115 Q100,125 100,135 T100,155" stroke="#45556C" strokeWidth="2" fill="none" />
                    <circle cx="100" cy="75" r="3" fill="#45556C" />
                    <circle cx="100" cy="95" r="3" fill="#45556C" />
                    <circle cx="100" cy="115" r="3" fill="#45556C" />
                    <circle cx="100" cy="135" r="3" fill="#45556C" />
                    <circle cx="100" cy="155" r="3" fill="#45556C" />
                </svg>
            </div>
            <div className="flex flex-col pt-2">
                <h3 className={`text-lg font-bold ${props.logotextclass != '' ? props.logotextclass : 'text-white'}`}>LaraNode</h3>
            </div>
        </div>
    );
}
