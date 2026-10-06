<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_services', function (Blueprint $table) {
            // Concepto de cotización que originó el contrato. UNIQUE: un concepto no se convierte dos veces.
            $table->foreignId('quote_item_id')->nullable()->unique()->after('service_id')->constrained()->restrictOnDelete();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->timestamp('converted_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('client_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quote_item_id');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('converted_at');
        });
    }
};
