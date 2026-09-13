/**
 * Breadcrumb — D14
 * Props:
 *   path (string)      — current directory path (backend-sandboxed)
 *   onNavigate (fn)    — called with fullPath when a segment button is clicked
 */
const Breadcrumb = ({ path, onNavigate }) => {
    // Build segments: always include root, then each non-empty path part
    const parts = (path || '/').split('/').filter((s) => s !== '');

    // [{ label, fullPath }, ...]
    const segments = [
        { label: '/', fullPath: '/' },
        ...parts.map((label, i) => ({
            label,
            fullPath: '/' + parts.slice(0, i + 1).join('/'),
        })),
    ];

    return (
        <div className="bg-white dark:bg-gray-850 py-3 px-6 dark:text-gray-300 text-gray-900 flex items-center space-x-1 text-xs">
            {segments.map((seg, i) => {
                const isLast = i === segments.length - 1;

                return (
                    <span key={seg.fullPath} className="flex items-center space-x-1">
                        {i > 0 && <span className="text-gray-400">/</span>}
                        {isLast ? (
                            <span className="font-semibold">{seg.label}</span>
                        ) : (
                            <button
                                type="button"
                                onClick={() => onNavigate(seg.fullPath)}
                                className="hover:text-indigo-500 hover:underline"
                            >
                                {seg.label}
                            </button>
                        )}
                    </span>
                );
            })}
        </div>
    );
};

export default Breadcrumb;
