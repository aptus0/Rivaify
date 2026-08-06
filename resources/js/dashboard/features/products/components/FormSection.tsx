import type { ReactNode } from 'react';
import { Card } from '../../../components/ui/Card';

export function FormSection({
  title,
  description,
  children,
  className = '',
}: {
  title: string;
  description?: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <Card className={className}>
      <div className="mb-5">
        <h2 className="text-base font-semibold text-dark">{title}</h2>
        {description && <p className="mt-1 text-sm text-muted">{description}</p>}
      </div>
      {children}
    </Card>
  );
}