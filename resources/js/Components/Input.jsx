import { useEffect, useRef } from 'react'

export default ({
    type = 'text',
    name,
    value,
    min,
    className,
    autoComplete,
    required,
    isFocused,
    handleChange,
    withIcon = false,
    placeholder,
    cursorColor,
    hasError,
}) => {
    const input = useRef()

    useEffect(() => {
        if (isFocused) {
            input.current.focus()
        }
    }, [])

    const baseClasses = `py-2 w-60 hover:bg-[#ffffff1a] rounded-lg bg-[#ffffff0d]`

    const inputStyle = {
        caretColor: cursorColor
    };
    
    const inputClassName = `${baseClasses} ${
        withIcon ? 'pl-11 pr-4' : 'px-4 py-2 text-md'
    } ${className} ${hasError ? 'border border-error-600 focus:ring focus:ring-1 focus:ring-error-600 focus:border-error-600' : 'border border-primary-900 focus:border-primary-800 focus:ring focus:ring-primary-800'}`;

    return (
        <input
            type={type}
            name={name}
            value={value}
            min={min}
            className={inputClassName}
            ref={input}
            autoComplete={autoComplete}
            required={required}
            onChange={(e) => handleChange(e)}
            placeholder={placeholder}
            style={inputStyle}
        />
    )
}