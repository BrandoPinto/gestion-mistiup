import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { Toaster, toast } from 'sonner';

/** Muestra como toast los mensajes flash de Laravel (session 'success' / 'error'). */
export function FlashToaster() {
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    return (
        <Toaster
            position="bottom-right"
            offset={16}
            mobileOffset={{ bottom: 72 }}
            toastOptions={{
                duration: 3500,
                classNames: {
                    toast: '!rounded-md !border !border-line !bg-surface !text-ink-900 !shadow-overlay !font-sans !text-base',
                    description: '!text-ink-500',
                    success: '[&_[data-icon]]:!text-success-600',
                    error: '[&_[data-icon]]:!text-danger-600',
                },
            }}
        />
    );
}
