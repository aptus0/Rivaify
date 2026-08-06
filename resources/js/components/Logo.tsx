import logoHorizontal from '../../images/rivaify-logo-horizontal.png';
import logoIcon from '../../images/rivaify-icon-512.png';

interface LogoProps {
  className?: string;
  /** 'icon' is for collapsed/small contexts (brief §23: sidebar collapse) — full wordmark otherwise. */
  variant?: 'horizontal' | 'icon';
  /** The horizontal wordmark's "Rivaify" text is a fixed dark navy baked into the
   * image — illegible on a dark panel (e.g. GuestLayout's brand side). There's no
   * light-color wordmark asset, so on dark backgrounds we pair the (already
   * bright-orange) icon with real white text instead of the image. */
  onDark?: boolean;
}

export function Logo({ className = '', variant = 'horizontal', onDark = false }: LogoProps) {
  if (variant === 'icon') {
    return <img src={logoIcon} alt="Rivaify" className={`h-8 w-8 shrink-0 ${className}`} />;
  }

  if (onDark) {
    return (
      <span className={`inline-flex shrink-0 items-center gap-2 ${className}`}>
        <img src={logoIcon} alt="" className="h-7 w-7" />
        <span className="text-xl font-extrabold tracking-tight text-white">Rivaify</span>
      </span>
    );
  }

  return <img src={logoHorizontal} alt="Rivaify" className={`h-7 w-auto shrink-0 ${className}`} />;
}
