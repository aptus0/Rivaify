function initials(name: string): string {
  const parts = name.trim().split(/\s+/);
  const first = parts[0]?.[0] ?? '';
  const last = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : '';
  return (first + last).toUpperCase();
}

export function Avatar({ name, className = '' }: { name: string; className?: string }) {
  return (
    <span
      className={`inline-flex h-8 w-8 items-center justify-center rounded-full bg-primary text-xs font-semibold text-white ${className}`}
    >
      {initials(name)}
    </span>
  );
}
