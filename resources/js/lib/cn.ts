type ClassValue = string | false | null | undefined;

/** Une clases condicionales. Sin tailwind-merge: los componentes no deben depender de sobrescribir clases en conflicto. */
export function cn(...classes: ClassValue[]): string {
    return classes.filter(Boolean).join(' ');
}
