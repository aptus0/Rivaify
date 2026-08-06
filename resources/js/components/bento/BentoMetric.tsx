interface BentoMetricProps {
  label: string;
  value: string;
  delta?: string;
  className?: string;
}

export function BentoMetric({ label, value, delta, className = '' }: BentoMetricProps) {
  return (
    <div className={`rounded-control border border-dark/[0.06] bg-white p-3 ${className}`}>
      <p className="text-[11px] font-medium text-dark/40">{label}</p>
      <p className="mt-1 text-lg font-bold text-dark">{value}</p>
      {delta && <p className="mt-0.5 text-[11px] font-semibold text-primary">{delta}</p>}
    </div>
  );
}
