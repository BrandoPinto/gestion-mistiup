<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            // Enlace público /q/{token}: se puede desactivar o regenerar.
            $table->boolean('public_enabled')->default(true)->after('public_token');
            // Registro mínimo de visitas del cliente (sin IP ni datos del navegador).
            $table->timestamp('first_viewed_at')->nullable()->after('public_enabled');
            $table->timestamp('last_viewed_at')->nullable()->after('first_viewed_at');
            $table->unsignedInteger('view_count')->default(0)->after('last_viewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['public_enabled', 'first_viewed_at', 'last_viewed_at', 'view_count']);
        });
    }
};
