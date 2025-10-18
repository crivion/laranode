import { useState, useRef, useEffect } from 'react';
import { Transition } from '@headlessui/react';

export default function SearchableDropdown({ 
    options = [], 
    value, 
    onChange, 
    placeholder = "Search...", 
    className = "",
    disabled = false,
    filterBy = "name"
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [filteredOptions, setFilteredOptions] = useState(options);
    const dropdownRef = useRef(null);
    const inputRef = useRef(null);

    useEffect(() => {
        const filtered = options.filter(option => 
            option[filterBy].toLowerCase().includes(searchTerm.toLowerCase())
        );
        setFilteredOptions(filtered);
    }, [searchTerm, options, filterBy]);

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
                setSearchTerm('');
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, []);

    const handleToggle = () => {
        if (disabled) return;
        setIsOpen(!isOpen);
        if (!isOpen) {
            setTimeout(() => inputRef.current?.focus(), 100);
        } else {
            setSearchTerm('');
        }
    };

    const handleSelect = (option) => {
        onChange(option);
        setIsOpen(false);
        setSearchTerm('');
    };

    const selectedOption = options.find(option => option.name === value);

    return (
        <div className={`relative ${className}`} ref={dropdownRef}>
            <button
                type="button"
                onClick={handleToggle}
                disabled={disabled}
                className={`
                    relative w-full cursor-default rounded-md bg-white py-1.5 pl-3 pr-10 text-left text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 sm:text-sm sm:leading-6
                    dark:bg-gray-900 dark:text-gray-300 dark:ring-gray-700 dark:focus:ring-indigo-600
                    ${disabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 dark:hover:bg-gray-800'}
                `}
            >
                <span className="block truncate">
                    {selectedOption ? selectedOption.name : placeholder}
                </span>
                <span className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                    <svg className="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clipRule="evenodd" />
                    </svg>
                </span>
            </button>

            <Transition
                show={isOpen}
                enter="transition ease-out duration-100"
                enterFrom="transform opacity-0 scale-95"
                enterTo="transform opacity-100 scale-100"
                leave="transition ease-in duration-75"
                leaveFrom="transform opacity-100 scale-100"
                leaveTo="transform opacity-0 scale-95"
            >
                <div className="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm dark:bg-gray-800">
                    <div className="px-3 py-2">
                        <input
                            ref={inputRef}
                            type="text"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            placeholder={placeholder}
                            className="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                        />
                    </div>
                    {filteredOptions.length === 0 ? (
                        <div className="relative cursor-default select-none px-4 py-2 text-gray-700 dark:text-gray-300">
                            No options found.
                        </div>
                    ) : (
                        filteredOptions.map((option) => (
                            <div
                                key={option.name}
                                onClick={() => handleSelect(option)}
                                className={`
                                    relative cursor-default select-none py-2 pl-3 pr-9 hover:bg-gray-100 dark:hover:bg-gray-700
                                    ${value === option.name ? 'bg-indigo-50 dark:bg-indigo-900' : ''}
                                `}
                            >
                                <span className={`block truncate ${value === option.name ? 'font-medium text-indigo-600 dark:text-indigo-300' : 'font-normal'}`}>
                                    {option.name}
                                </span>
                                {option.charset && (
                                    <span className="text-xs text-gray-500 dark:text-gray-400 ml-2">
                                        ({option.charset})
                                    </span>
                                )}
                                {value === option.name && (
                                    <span className="absolute inset-y-0 right-0 flex items-center pr-4 text-indigo-600 dark:text-indigo-300">
                                        <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clipRule="evenodd" />
                                        </svg>
                                    </span>
                                )}
                            </div>
                        ))
                    )}
                </div>
            </Transition>
        </div>
    );
}
