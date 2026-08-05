const TOTAL_STEPS = 8;

export function ProgressBar({ step, label }: { step: number; label: string }) {
  const percent = Math.min(100, Math.round((step / TOTAL_STEPS) * 100));

  return (
    <div className="mb-8">
      <div className="mb-2 flex items-center justify-between text-sm text-neutral-600">
        <span>{label}</span>
        <span>
          {step}/{TOTAL_STEPS}
        </span>
      </div>
      <div className="h-2 w-full rounded-full bg-neutral-200">
        <div
          className="h-2 rounded-full bg-neutral-900 transition-all"
          style={{ width: `${percent}%` }}
        />
      </div>
    </div>
  );
}
