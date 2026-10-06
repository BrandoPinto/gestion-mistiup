<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Datos de la empresa emisora (una sola fila). Alimenta cotizaciones, PDF y valores por defecto.
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('trade_name')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('ruc', 11)->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->json('bank_accounts')->nullable(); // [{bank, currency, number, cci, holder}]
            $table->string('yape_phone', 20)->nullable();
            $table->string('plin_phone', 20)->nullable();
            $table->string('wallet_holder')->nullable(); // Titular de Yape/Plin.
            $table->text('quote_intro')->nullable();
            $table->text('quote_terms')->nullable();
            $table->decimal('default_tax_rate', 5, 2)->default(18.00);
            $table->char('default_currency', 3)->default('PEN');
            $table->unsignedSmallInteger('quote_validity_days')->default(15);
            $table->timestamps();
        });

        DB::table('company_profiles')->insert(['created_at' => now(), 'updated_at' => now()]);

        // Numeración correlativa por tipo de documento y año (COT-2026-0001). Se bloquea la fila al numerar.
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['type', 'year']);
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique(); // Visible; independiente del id.
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence');
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('draft'); // draft|sent|partially_converted|converted|cancelled
            $table->char('currency', 3);
            $table->date('issue_date');
            $table->date('valid_until');
            $table->date('delivery_date')->nullable();
            $table->decimal('tax_rate', 5, 2);
            $table->string('global_discount_type', 10)->nullable(); // percent|amount
            $table->decimal('global_discount_value', 12, 2)->nullable();
            // Importes calculados por QuoteCalculator (precios con IGV incluido). Se guardan: el documento es una foto.
            $table->decimal('subtotal', 12, 2);
            $table->decimal('global_discount_amount', 12, 2);
            $table->decimal('discount_total', 12, 2);
            $table->decimal('total', 12, 2);
            $table->decimal('tax_base', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->text('intro')->nullable();
            $table->text('observations')->nullable();
            $table->text('terms')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('public_token', 64)->unique(); // Enlace público (Fase 8): aleatorio, nunca el id.
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['year', 'sequence']);
            $table->index(['status', 'issue_date']);
            $table->index(['client_id', 'status']);
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->string('discount_type', 10)->nullable(); // percent|amount
            $table->decimal('discount_value', 12, 2)->nullable();
            $table->decimal('line_gross', 12, 2);
            $table->decimal('line_discount', 12, 2);
            $table->decimal('line_total', 12, 2);
            // Modalidad sugerida (del catálogo) para la conversión a servicios contratados (Fase 9).
            $table->string('suggested_billing_type', 20)->nullable();
            $table->string('suggested_interval_unit', 10)->nullable();
            $table->unsignedSmallInteger('suggested_interval_count')->nullable();
            $table->timestamps();

            $table->index(['quote_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('company_profiles');
    }
};
