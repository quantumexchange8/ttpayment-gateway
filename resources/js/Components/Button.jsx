import { Link } from '@inertiajs/react'

export default ({
    type = 'submit',
    className = '',
    processing,
    children,
    href,
    target,
    external,
    variant = 'primary',
    size = 'base',
    iconOnly,
    squared = false,
    pill = false,
    srText,
    onClick,
    disabled,
}) => {
    const baseClasses = `inline-flex items-center transition-colors font-medium text-center select-none disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none`

    let variantClasses = ``

    switch (variant) {
        case 'secondary':
            variantClasses = `bg-[#ffffff0d] text-white hover:bg-[#ffffff1a] focus:ring-purple-500 dark:text-white disabled:text-gray-700 disabled:bg-[#ffffff0d]`
            break
        case 'success':
            variantClasses = `bg-green-500 text-white hover:bg-green-600 focus:ring-green-500`
            break
        case 'danger':
            variantClasses = `bg-error-600 text-white hover:bg-error-700 focus:ring-red-500 disabled:text-error-600 disabled:bg-error-800`
            break
        case 'warning':
            variantClasses = `bg-yellow-500 text-white hover:bg-yellow-600 focus:ring-yellow-500`
            break
        case 'info':
            variantClasses = `bg-cyan-500 text-white hover:bg-cyan-600 focus:ring-cyan-500`
            break
        case 'black':
            variantClasses = `bg-[#03071266] text-white hover:text-white hover:bg-[#030712cc] focus:ring-black disabled:text-gray-700 disabled:bg-[#03071266]`
            break
        default:
            variantClasses = `bg-primary-700 text-white hover:bg-primary-800 disabled:text-primary-700 disabled:bg-primary-900`
    }

    const sizeClasses = `${
        size == 'sm' ? (iconOnly ? 'p-1.5' : 'px-4 py-2 text-sm font-semibold') : ''
    } ${
        size == 'lg' ? (iconOnly ? 'p-3' : 'px-4 py-3 text-sm font-semibold') : ''
    }`

    const roundedClasses = `${!squared && !pill ? 'rounded-md' : ''} ${
        pill ? 'rounded-full' : ''
    }`

    const iconSizeClasses = `${size == 'sm' ? 'w-5 h-5' : ''} ${
        size == 'base' ? 'w-6 h-6' : ''
    } ${size == 'lg' ? 'w-7 h-7' : ''}`

    if (href) {
        const Tag = external ? 'a' : Link

        return (
            <Tag
                target={target}
                href={href}
                className={`${baseClasses} ${sizeClasses} ${variantClasses} ${roundedClasses} ${className} ${
                    processing ? 'pointer-events-none opacity-50' : ''
                }`}
                disabled={disabled}
            >
                {children}
                {iconOnly && <span className="sr-only">{srText ?? ''}</span>}
            </Tag>
        )
    }

    return (
        <button
            type={type}
            className={`${baseClasses} ${sizeClasses} ${variantClasses} ${roundedClasses} ${className}`}
            disabled={processing || disabled}
            onClick={onClick}
        >
            {children}
            {iconOnly && <span className="sr-only">{srText ?? ''}</span>}
        </button>
    )
}