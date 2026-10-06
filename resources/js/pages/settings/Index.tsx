import { PageHeader } from '@/components/layout/PageHeader';
import { cn } from '@/lib/cn';
import { Link } from '@inertiajs/react';
import { BellRing, Building2, CalendarClock, ChevronRight, CreditCard, UserCog, type LucideIcon } from 'lucide-react';

type Section = {
    title: string;
    description: string;
    icon: LucideIcon;
    href?: string;
    phase?: string;
};

export default function SettingsIndex() {
    const sections: Section[] = [
        {
            title: 'Recordatorios',
            description: 'Cuántos días antes y después del vencimiento se emiten los avisos.',
            icon: BellRing,
            href: route('settings.reminders'),
        },
        {
            title: 'Mi empresa',
            description: 'Logo, datos fiscales, cuentas bancarias y textos para cotizaciones.',
            icon: Building2,
            href: route('settings.company'),
        },
        {
            title: 'Métodos de pago',
            description: 'Efectivo, transferencia, Yape, Plin… y el orden en que aparecen.',
            icon: CreditCard,
            href: route('settings.payment-methods'),
        },
        {
            title: 'Cobros',
            description: 'Con cuántos días de anticipación se generan los cobros.',
            icon: CalendarClock,
            href: route('settings.billing'),
        },
        {
            title: 'Usuarios',
            description: 'Accesos al sistema, contraseñas y activación.',
            icon: UserCog,
            href: route('users.index'),
        },
    ];

    return (
        <>
            <PageHeader title="Configuración" eyebrow="Sistema" />
            <ul className="divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface">
                {sections.map((section) => {
                    const Icon = section.icon;
                    const content = (
                        <>
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-md border border-line bg-cream-50 text-ink-500">
                                <Icon className="size-5" aria-hidden />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block text-md font-medium text-ink-900">{section.title}</span>
                                <span className="block text-sm text-ink-500">{section.description}</span>
                            </span>
                            {section.href ? (
                                <ChevronRight className="size-5 text-ink-400" aria-hidden />
                            ) : (
                                <span className="text-xs text-ink-400">{section.phase}</span>
                            )}
                        </>
                    );

                    return (
                        <li key={section.title}>
                            {section.href ? (
                                <Link href={section.href} className="flex items-center gap-4 px-5 py-4 transition-colors hover:bg-cream-50">
                                    {content}
                                </Link>
                            ) : (
                                <div className={cn('flex items-center gap-4 px-5 py-4 opacity-70')}>{content}</div>
                            )}
                        </li>
                    );
                })}
            </ul>
        </>
    );
}
