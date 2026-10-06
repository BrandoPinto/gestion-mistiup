<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 60);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Métodos iniciales: se insertan en la migración para que existan también en producción.
        $now = now();
        DB::table('payment_methods')->insert(array_map(fn (array $row) => [...$row, 'created_at' => $now, 'updated_at' => $now], [
            ['code' => 'cash', 'name' => 'Efectivo', 'sort_order' => 1],
            ['code' => 'transfer', 'name' => 'Transferencia', 'sort_order' => 2],
            ['code' => 'yape', 'name' => 'Yape', 'sort_order' => 3],
            ['code' => 'plin', 'name' => 'Plin', 'sort_order' => 4],
            ['code' => 'other', 'name' => 'Otro', 'sort_order' => 5],
        ]));

        // COBRO: dinero que se debe recibir. Nunca se borra: se cancela.
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_service_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description');
            $table->unsignedInteger('cycle_number')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('due_date');
            $table->char('currency', 3);
            $table->decimal('amount', 12, 2); // Total con IGV incluido.
            $table->decimal('tax_rate', 5, 2)->default(0); // Referencia del desglose.
            $table->decimal('tax_amount', 12, 2)->default(0);
            // Caché derivada de SUM(pagos no anulados); solo la escribe ChargeSettlement.
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('status', 20)->default('pending'); // pending|partial|paid|cancelled. "Vencido" se calcula.
            $table->date('paid_on')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Idempotencia de la generación: un contrato no puede tener dos cobros del mismo ciclo.
            $table->unique(['client_service_id', 'cycle_number']);
            $table->index(['status', 'due_date']);
            $table->index(['client_id', 'status']);
        });

        // PAGO: dinero realmente recibido. Nunca se borra: se anula.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('charge_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->date('paid_on');
            $table->char('currency', 3);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();

            $table->index('charge_id');
            $table->index(['paid_on', 'currency']);
            $table->index(['client_id', 'paid_on']);
        });

        // Archivos adjuntos (comprobantes de pago; luego contratos, gastos…). Se guardan en disco privado.
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('disk', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('charges');
        Schema::dropIfExists('payment_methods');
    }
};
