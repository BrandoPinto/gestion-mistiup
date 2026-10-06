<?php

namespace App\Domain\Shared\Files;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Guarda archivos subidos en el disco privado con nombre aleatorio (nunca el nombre original)
 * y extensión derivada del MIME real detectado, no de la que trae el archivo.
 */
class StoreAttachment
{
    /**
     * @return array{disk: string, path: string, original_name: string, mime_type: string, size: int}
     */
    public function storeFile(UploadedFile $file): array
    {
        $disk = config('billing.attachments.disk');
        $extension = $file->guessExtension() ?? throw new RuntimeException('Tipo de archivo no reconocido.');
        $directory = 'attachments/'.now()->format('Y/m');
        $path = $file->storeAs($directory, Str::uuid()->toString().'.'.$extension, $disk);

        if ($path === false) {
            throw new RuntimeException('No se pudo guardar el archivo.');
        }

        return [
            'disk' => $disk,
            'path' => $path,
            // Solo para mostrar/descargar; se recorta y limpia de caracteres de control.
            'original_name' => Str::limit(preg_replace('/[\x00-\x1F\x7F]/u', '', $file->getClientOriginalName()) ?: 'archivo.'.$extension, 200, ''),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => (int) $file->getSize(),
        ];
    }

    /**
     * @param  array{disk: string, path: string, original_name: string, mime_type: string, size: int}  $stored
     */
    public function attach(Model $owner, array $stored, int $userId): Attachment
    {
        $attachment = (new Attachment)->forceFill([
            'attachable_type' => $owner->getMorphClass(),
            'attachable_id' => $owner->getKey(),
            ...$stored,
            'uploaded_by' => $userId,
        ]);
        $attachment->save();

        return $attachment;
    }

    /**
     * @param  array{disk: string, path: string}  $stored
     */
    public function discard(array $stored): void
    {
        Storage::disk($stored['disk'])->delete($stored['path']);
    }
}
