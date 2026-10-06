import { buttonClasses } from '@/components/ui/Button';
import { QuotePreview } from '@/features/quotes/QuotePreview';
import type { CompanyBlock, QuoteDocument } from '@/features/quotes/types';
import { Head } from '@inertiajs/react';
import { CircleAlert, FileDown } from 'lucide-react';

type PublicQuoteProps = {
    document: QuoteDocument;
    company: CompanyBlock;
    cancelled: boolean;
    pdf_url: string;
};

/**
 * Vista pública de una cotización (sin sesión, sin layout administrativo). Solo lectura:
 * no hay acciones sobre la cotización ni enlaces al sistema.
 */
export default function PublicQuote({ document, company, cancelled, pdf_url }: PublicQuoteProps) {
    return (
        <>
            <Head title={`Cotización ${document.number}`}>
                <meta name="robots" content="noindex, nofollow" />
            </Head>

            <div className="min-h-dvh bg-canvas">
                <header className="sticky top-0 z-10 border-b border-line bg-surface/95 backdrop-blur-sm">
                    <div className="mx-auto flex h-14 max-w-[880px] items-center justify-between gap-3 px-4">
                        <div className="min-w-0">
                            <p className="truncate text-base font-semibold text-ink-900">{company.name}</p>
                            <p className="numeric truncate text-xs text-ink-500">Cotización {document.number}</p>
                        </div>
                        <a href={pdf_url} target="_blank" rel="noopener" className={buttonClasses('primary')}>
                            <FileDown /> Descargar PDF
                        </a>
                    </div>
                </header>

                <main className="mx-auto max-w-[880px] px-0 py-6 sm:px-4 sm:py-10">
                    {cancelled && (
                        <div
                            role="status"
                            className="mx-4 mb-4 flex items-start gap-2 rounded-md border border-warning-600/30 bg-warning-50 px-4 py-3 text-base text-warning-700 sm:mx-0"
                        >
                            <CircleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
                            Esta cotización ya no está vigente. Comunícate con {company.name} para una propuesta actualizada.
                        </div>
                    )}
                    <div className="overflow-hidden border-line bg-white sm:rounded-md sm:border sm:shadow-subtle">
                        <QuotePreview document={document} company={company} />
                    </div>
                    <p className="mt-6 px-4 text-center text-xs text-ink-400">Documento generado por {company.name}.</p>
                </main>
            </div>
        </>
    );
}
