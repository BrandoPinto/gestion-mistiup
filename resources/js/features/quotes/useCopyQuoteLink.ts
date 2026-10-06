import { copyText } from '@/lib/clipboard';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';

/**
 * Copia el enlace público. No cambia el estado por sí solo: si la cotización está en borrador,
 * el aviso ofrece marcarla como enviada con un clic.
 */
export function useCopyQuoteLink(quote: { id: number; status: string; public_url: string | null }) {
    return async function copy() {
        if (!quote.public_url) {
            toast.error('El enlace público está desactivado. Actívalo desde la ficha de la cotización.');
            return;
        }

        const copied = await copyText(quote.public_url);

        if (!copied) {
            // Sin acceso al portapapeles (p. ej. sitio sin HTTPS): se muestra el enlace seleccionado para copiarlo a mano.
            window.prompt('Copia el enlace de la cotización:', quote.public_url);
            return;
        }

        if (quote.status === 'draft') {
            toast.success('Enlace copiado', {
                description: '¿Ya se la enviaste al cliente?',
                action: {
                    label: 'Marcar como enviada',
                    onClick: () => router.post(route('quotes.send', quote.id), {}, { preserveScroll: true }),
                },
                duration: 8000,
            });
            return;
        }

        toast.success('Enlace copiado');
    };
}
