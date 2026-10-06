import { cn } from '@/lib/cn';

/** Bloque de carga. Darle el tamaño real del contenido que reemplaza. */
export function Skeleton({ className }: { className?: string }) {
    return <span aria-hidden className={cn('block animate-pulse rounded-sm bg-cream-200/70', className)} />;
}
