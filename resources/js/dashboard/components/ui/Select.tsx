import type { SelectHTMLAttributes } from 'react';

interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
  label: string;
  error?: string;
  placeholder?: string;
}

export function Select({ label, error, placeholder, className = '', id, children, ...props }: SelectProps) {
  const selectId = id ?? props.name;

  return (
    <div className="flex flex-col gap-1">
      <label htmlFor={selectId} className="text-sm font-medium text-neutral-700">
        {label}
      </label>
      <select
        id={selectId}
        className={`rounded-md border bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-neutral-400 ${
          error ? 'border-red-500' : 'border-neutral-300'
        } ${className}`}
        {...props}
      >
        {placeholder && (
          <option value="" disabled hidden>
            {placeholder}
          </option>
        )}
        {children}
      </select>
      {error && <span className="text-sm text-red-600">{error}</span>}
    </div>
  );
}
