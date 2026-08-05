interface LogoProps {
  className?: string;
}

export function Logo({ className = '' }: LogoProps) {
  return (
    <span className={`text-xl font-extrabold tracking-tight text-dark ${className}`}>
      Riva<span className="text-primary">ify</span>
    </span>
  );
}
