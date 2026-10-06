import { formatPhone, whatsappUrl } from '@/lib/phone';
import { Mail, MessageCircle, Phone } from 'lucide-react';

type ContactLinksProps = {
    whatsapp: string | null;
    phone: string | null;
    email: string | null;
};

/** Accesos directos de contacto. WhatsApp abre wa.me en otra pestaña. */
export function ContactLinks({ whatsapp, phone, email }: ContactLinksProps) {
    if (!whatsapp && !phone && !email) {
        return <span className="text-ink-400">—</span>;
    }

    return (
        <div className="flex flex-col gap-0.5 text-sm">
            {whatsapp && (
                <a
                    href={whatsappUrl(whatsapp)}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="numeric inline-flex items-center gap-1.5 text-ink-700 transition-colors hover:text-brand-700"
                >
                    <MessageCircle className="size-3.5 text-success-600" aria-hidden />
                    {formatPhone(whatsapp)}
                </a>
            )}
            {!whatsapp && phone && (
                <a href={`tel:${phone}`} className="numeric inline-flex items-center gap-1.5 text-ink-700 transition-colors hover:text-brand-700">
                    <Phone className="size-3.5 text-ink-400" aria-hidden />
                    {formatPhone(phone)}
                </a>
            )}
            {email && (
                <a href={`mailto:${email}`} className="inline-flex max-w-56 items-center gap-1.5 truncate text-ink-500 transition-colors hover:text-brand-700">
                    <Mail className="size-3.5 shrink-0 text-ink-400" aria-hidden />
                    <span className="truncate">{email}</span>
                </a>
            )}
        </div>
    );
}
