<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo: plantillas de servicio. No es un contrato; sus valores solo se sugieren al contratar o cotizar.
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('default_price', 12, 2)->nullable(); // Precio con IGV incluido.
            $table->char('default_currency', 3)->default('PEN');
            $table->string('default_billing_type', 20)->default('one_time');
            $table->string('default_interval_unit', 10)->nullable();
            $table->unsignedSmallInteger('default_interval_count')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);
        });

        // Garantía en base de datos de la recurrencia normalizada (MySQL/MariaDB; SQLite de pruebas no admite ADD CONSTRAINT).
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(<<<'SQL'
                ALTER TABLE services ADD CONSTRAINT services_recurrence_normalized CHECK (
                    (default_billing_type = 'one_time' AND default_interval_unit IS NULL AND default_interval_count IS NULL)
                    OR (default_billing_type = 'recurring' AND default_interval_count >= 1 AND (
                        default_interval_unit = 'year'
                        OR (default_interval_unit = 'month' AND MOD(default_interval_count, 12) <> 0)
                    ))
                )
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
