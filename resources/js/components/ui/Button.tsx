import type { MouseEventHandler, ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';

type ButtonVariant = 'primary' | 'dark' | 'secondary' | 'ghost' | 'outlineOnDark';
type ButtonSize = 'md' | 'lg';

const VARIANT_CLASSES: Record<ButtonVariant, string> = {
  primary:
    'bg-primary text-white shadow-[0_8px_24px_-8px_rgba(255,107,0,0.55)] hover:bg-primary-hover',
  dark: 'bg-dark text-white hover:bg-primary',
  secondary: 'border border-dark/10 bg-white text-dark hover:border-dark/20 hover:bg-surface',
  ghost: 'text-dark/70 hover:text-dark',
  /** Secondary-style CTA for dark sections (Final CTA) — a distinct variant
   * rather than overriding `secondary` via className, since conflicting
   * bg-white/bg-transparent utilities don't reliably resolve by JSX order. */
  outlineOnDark: 'border border-white/15 bg-transparent text-white hover:border-white/30 hover:bg-white/5',
};

const SIZE_CLASSES: Record<ButtonSize, string> = {
  md: 'px-5 py-2.5 text-sm',
  lg: 'px-7 py-3.5 text-sm',
};

interface ButtonProps {
  variant?: ButtonVariant;
  size?: ButtonSize;
  icon?: LucideIcon;
  iconPosition?: 'left' | 'right';
  fullWidthOnMobile?: boolean;
  className?: string;
  children: ReactNode;
  href?: string;
  target?: string;
  rel?: string;
  type?: 'button' | 'submit';
  onClick?: MouseEventHandler<HTMLButtonElement | HTMLAnchorElement>;
  'aria-label'?: string;
}

/** Shared CTA primitive — a 1–2px hover lift via transform (never layout
 * properties), matching the microinteraction rules for the whole site.
 * Renders an <a> when `href` is given, otherwise a <button>. */
export function Button({
  variant = 'primary',
  size = 'md',
  icon: Icon,
  iconPosition = 'right',
  fullWidthOnMobile = false,
  className = '',
  children,
  href,
  target,
  rel,
  type = 'button',
  onClick,
  ...rest
}: ButtonProps) {
  const classes = `group inline-flex items-center justify-center gap-2 rounded-full font-semibold transition-all duration-200 hover:-translate-y-0.5 ${VARIANT_CLASSES[variant]} ${SIZE_CLASSES[size]} ${fullWidthOnMobile ? 'w-full sm:w-auto' : ''} ${className}`;

  const iconEl = Icon ? (
    <Icon
      className={`h-4 w-4 transition-transform duration-200 ${
        iconPosition === 'right' ? 'group-hover:translate-x-0.5' : 'group-hover:-translate-x-0.5'
      }`}
    />
  ) : null;

  const content = (
    <>
      {iconPosition === 'left' && iconEl}
      {children}
      {iconPosition === 'right' && iconEl}
    </>
  );

  if (href) {
    return (
      <a href={href} target={target} rel={rel} className={classes} onClick={onClick} {...rest}>
        {content}
      </a>
    );
  }

  return (
    <button type={type} className={classes} onClick={onClick} {...rest}>
      {content}
    </button>
  );
}
