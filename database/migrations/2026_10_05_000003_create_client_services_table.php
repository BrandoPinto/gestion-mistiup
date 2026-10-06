<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Servicio contratado por un cliente: condiciones reales (copiadas del catálogo y editables).
        Schema::create('client_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->char('currency', 3);
            $table->decimal('price', 12, 2); // Por ciclo (o total si es pago único), con IGV incluido.
            $table->string('billing_type', 20);
            $table->string('interval_unit', 10)->nullable();
            $table->unsignedSmallInteger('interval_count')->nullable();
            $table->date('start_date'); // Ancla del calendario: el ciclo 1 vence aquí.
            $table->unsignedSmallInteger('term_months')->nullable(); // null = sin fecha de fin.
            $table->date('end_date')->nullable(); // Derivada de start_date + term_months; se guarda para consultar.
            $table->unsignedInteger('next_cycle_number')->default(1); // Próximo ciclo cuyo cobro falta generar.
            $table->date('next_charge_date')->nullable(); // Vencimiento de ese ciclo (puede moverse a mano).
            $table->string('status', 20)->default('active');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index(['status', 'next_charge_date']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(<<<'SQL'
                ALTER TABLE client_services ADD CONSTRAINT client_services_recurrence_normalized CHECK (
                    (billing_type = 'one_time' AND interval_unit IS NULL AND interval_count IS NULL AND term_months IS NULL)
                    OR (billing_type = 'recurring' AND interval_count >= 1 AND (
                        interval_unit = 'year'
                        OR (interval_unit = 'month' AND MOD(interval_count, 12) <> 0)
                    ))
                )
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_services');
    }
};
