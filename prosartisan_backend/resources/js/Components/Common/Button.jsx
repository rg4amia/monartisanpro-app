import React from 'react';

export default function Button({
 children,
 variant = 'primary',
 size = 'md',
 className = '',
 disabled = false,
 icon: Icon,
 ...props
}) {
 const baseClasses = 'inline-flex items-center justify-center font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2';

 const variants = {
  primary: 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500 disabled:bg-blue-300',
  secondary: 'bg-gray-200 text-gray-900 hover:bg-gray-300 focus:ring-gray-500 disabled:bg-gray-100',
  danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 disabled:bg-red-300',
  success: 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500 disabled:bg-green-300',
  warning: 'bg-yellow-600 text-white hover:bg-yellow-700 focus:ring-yellow-500 disabled:bg-yellow-300',
  outline: 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:ring-blue-500 disabled:bg-gray-50',
 };

 const sizes = {
  xs: 'px-2.5 py-1.5 text-xs',
  sm: 'px-3 py-2 text-sm',
  md: 'px-4 py-2 text-sm',
  lg: 'px-4 py-2 text-base',
  xl: 'px-6 py-3 text-base',
 };

 const iconSizes = {
  xs: 'h-3 w-3',
  sm: 'h-4 w-4',
  md: 'h-4 w-4',
  lg: 'h-5 w-5',
  xl: 'h-5 w-5',
 };

 const disabledClasses = disabled ? 'cursor-not-allowed opacity-50' : '';

 return (
  <button
   className={`${baseClasses} ${variants[variant]} ${sizes[size]} ${disabledClasses} ${className}`}
   disabled={disabled}
   {...props}
  >
   {Icon && (
    <Icon className={`${iconSizes[size]} ${children ? 'mr-2' : ''}`} />
   )}
   {children}
  </button>
 );
}
