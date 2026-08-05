import type { ButtonHTMLAttributes } from 'react';

export function Button({
  className = '',
  disabled,
  children,
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button
      disabled={disabled}
      className={`w-full rounded-md bg-neutral-900 px-4 py-2 font-medium text-white transition hover:bg-neutral-700 disabled:cursor-not-allowed disabled:opacity-50 ${className}`}
      {...props}
    >
      {children}
    </button>
  );
}
