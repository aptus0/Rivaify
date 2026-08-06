import { useEffect, useRef } from 'react';

interface Particle {
  x: number;
  y: number;
  vx: number;
  vy: number;
  radius: number;
  depth: number; // 0..1, fakes a 3D parallax feel — deeper dots are smaller/dimmer and move less
}

const PARTICLE_COUNT = 90;
const LINK_DISTANCE = 110;
const MOUSE_RADIUS = 140;

/**
 * Small canvas dot field for GuestLayout's background (brief: "3d efekti,
 * noktalı, küçük, hareket eden, mouse imleciyle"). Deliberately plain
 * canvas 2D rather than a Three.js/WebGL dependency — the "3D" feel comes
 * from per-dot depth (size/opacity/parallax), not real 3D geometry, which
 * keeps this at zero new dependencies for a purely decorative background.
 */
export function ParticleBackground({ className = '' }: { className?: string }) {
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let width = 0;
    let height = 0;
    let dpr = Math.min(window.devicePixelRatio || 1, 2);
    let particles: Particle[] = [];
    const mouse = { x: -9999, y: -9999 };

    function resize() {
      const canvasEl = canvasRef.current;
      if (!canvasEl) return;
      const rect = canvasEl.getBoundingClientRect();
      width = rect.width;
      height = rect.height;
      dpr = Math.min(window.devicePixelRatio || 1, 2);
      canvasEl.width = width * dpr;
      canvasEl.height = height * dpr;
      ctx?.scale(dpr, dpr);

      particles = Array.from({ length: PARTICLE_COUNT }, () => {
        const depth = Math.random();
        return {
          x: Math.random() * width,
          y: Math.random() * height,
          vx: (Math.random() - 0.5) * (0.15 + depth * 0.2),
          vy: (Math.random() - 0.5) * (0.15 + depth * 0.2),
          radius: 0.8 + depth * 1.8,
          depth,
        };
      });
    }

    function handlePointerMove(event: PointerEvent) {
      const rect = canvas!.getBoundingClientRect();
      mouse.x = event.clientX - rect.left;
      mouse.y = event.clientY - rect.top;
    }
    function handlePointerLeave() {
      mouse.x = -9999;
      mouse.y = -9999;
    }

    function step() {
      if (!ctx) return;
      ctx.clearRect(0, 0, width, height);

      for (const p of particles) {
        p.x += p.vx;
        p.y += p.vy;
        if (p.x < 0) p.x = width;
        if (p.x > width) p.x = 0;
        if (p.y < 0) p.y = height;
        if (p.y > height) p.y = 0;

        const dx = p.x - mouse.x;
        const dy = p.y - mouse.y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        let drawX = p.x;
        let drawY = p.y;
        let boost = 0;
        if (dist < MOUSE_RADIUS) {
          const force = (1 - dist / MOUSE_RADIUS) * 14;
          const angle = Math.atan2(dy, dx);
          drawX = p.x + Math.cos(angle) * force;
          drawY = p.y + Math.sin(angle) * force;
          boost = 1 - dist / MOUSE_RADIUS;
        }

        const opacity = 0.15 + p.depth * 0.35 + boost * 0.4;
        ctx.beginPath();
        ctx.arc(drawX, drawY, p.radius + boost * 1.5, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(255, 107, 0, ${Math.min(opacity, 0.9)})`;
        ctx.fill();
      }

      for (let i = 0; i < particles.length; i++) {
        for (let j = i + 1; j < particles.length; j++) {
          const a = particles[i];
          const b = particles[j];
          const dx = a.x - b.x;
          const dy = a.y - b.y;
          const dist = Math.sqrt(dx * dx + dy * dy);
          if (dist < LINK_DISTANCE) {
            ctx.beginPath();
            ctx.moveTo(a.x, a.y);
            ctx.lineTo(b.x, b.y);
            ctx.strokeStyle = `rgba(17, 17, 17, ${0.05 * (1 - dist / LINK_DISTANCE)})`;
            ctx.lineWidth = 1;
            ctx.stroke();
          }
        }
      }
    }

    let frameId: number;
    function loop() {
      step();
      frameId = requestAnimationFrame(loop);
    }

    resize();
    window.addEventListener('resize', resize);
    window.addEventListener('pointermove', handlePointerMove);
    window.addEventListener('pointerleave', handlePointerLeave);

    if (reduceMotion) {
      step();
    } else {
      loop();
    }

    return () => {
      window.removeEventListener('resize', resize);
      window.removeEventListener('pointermove', handlePointerMove);
      window.removeEventListener('pointerleave', handlePointerLeave);
      if (frameId) cancelAnimationFrame(frameId);
    };
  }, []);

  return (
    <canvas
      ref={canvasRef}
      className={`pointer-events-none absolute inset-0 h-full w-full ${className}`}
      aria-hidden="true"
    />
  );
}
