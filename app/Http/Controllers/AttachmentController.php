<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve adjuntos del disco privado solo a usuarios autorizados. Imágenes y PDF se muestran en línea;
 * nosniff evita que el navegador reinterprete el tipo de archivo.
 */
class AttachmentController extends Controller
{
    private const INLINE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function show(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        $disposition = in_array($attachment->mime_type, self::INLINE_TYPES, true) ? 'inline' : 'attachment';

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-store',
        ], $disposition);
    }
}
