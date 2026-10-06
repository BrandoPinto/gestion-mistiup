<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reglas configurables: "avisar N días antes del vencimiento", "N días después de vencido"…
        Schema::create('reminder_rules', function (Blueprint $table) {
            $table->id();
            $table->string('event', 30); // charge_due | charge_overdue | contract_end
            $table->unsignedSmallInteger('days');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event', 'days']);
        });

        $now = now();
        DB::table('reminder_rules')->insert(array_map(fn (array $row) => [...$row, 'created_at' => $now, 'updated_at' => $now], [
            ['event' => 'charge_due', 'days' => 30],
            ['event' => 'charge_due', 'days' => 7],
            ['event' => 'charge_overdue', 'days' => 1],
            ['event' => 'contract_end', 'days' => 30],
        ]));

        // Idempotencia: una fila por (regla, entidad, fecha objetivo). Si el scheduler corre dos veces,
        // el UNIQUE impide un segundo aviso. Si la fecha del cobro cambia, es otra fila y se vuelve a avisar.
        Schema::create('reminder_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_rule_id')->constrained()->cascadeOnDelete();
            $table->morphs('remindable');
            $table->date('target_date');
            $table->boolean('notified')->default(true); // false = ventana cubierta por un aviso más cercano.
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['reminder_rule_id', 'remindable_type', 'remindable_id', 'target_date'], 'reminder_dispatches_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_dispatches');
        Schema::dropIfExists('reminder_rules');
    }
};
