export default ({ forInput, value, className, children }) => {
    return (
        <label
            htmlFor={forInput}
            className={`block text-sm font-medium  ${className}`}
        >
            {value ? value : children}
        </label>
    )
}