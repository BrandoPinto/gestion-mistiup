<?php

namespace App\Domain\Shared\Documents;

use Illuminate\Support\Facades\DB;

/**
 * Numeración correlativa por tipo y año (COT-2026-0001). Debe llamarse dentro de una transacción:
 * bloquea la fila del contador, así dos documentos simultáneos nunca reciben el mismo número.
 * Se permiten huecos (un borrador cancelado conserva su número; los números no se reciclan).
 */
class DocumentNumberGenerator
{
    /**
     * @return array{number: string, sequence: int}
     */
    public function next(string $type, string $prefix, int $year): array
    {
        DB::table('document_sequences')->insertOrIgnore([
            'type' => $type,
            'year' => $year,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('document_sequences')->where('type', $type)->where('year', $year)->lockForUpdate()->first();
        $sequence = $row->last_number + 1;

        DB::table('document_sequences')->where('id', $row->id)->update(['last_number' => $sequence, 'updated_at' => now()]);

        return [
            'number' => sprintf('%s-%d-%04d', $prefix, $year, $sequence),
            'sequence' => $sequence,
        ];
    }
}
