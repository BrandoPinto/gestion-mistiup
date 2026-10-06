<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Archivo adjunto en disco privado. Solo se sirve a través de AttachmentController (con autorización).
 */
class Attachment extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
