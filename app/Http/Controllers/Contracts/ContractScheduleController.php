<?php

namespace App\Http\Controllers\Contracts;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Billing\Enums\IntervalUnit;
use App\Domain\Billing\Recurrence;
use App\Domain\Billing\Services\RecurrenceCalculator;
use App\Domain\Contracts\Services\ContractSchedule;
use App\Domain\Shared\Dates\BusinessClock;
use App\Http\Controllers\Controller;
use App\Models\ClientService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Vista previa del calendario mientras se llena el formulario del contrato (JSON).
 * Las fechas se calculan solo en el servidor para que la vista previa coincida con lo que se guardará.
 */
class ContractScheduleController extends Controller
{
    public function __invoke(Request $request, ContractSchedule $schedule, RecurrenceCalculator $calculator, BusinessClock $clock): JsonResponse
    {
        Gate::authorize('create', ClientService::class);

        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'billing_type' => ['required', Rule::enum(BillingType::class)],
            'interval_unit' => ['nullable', Rule::enum(IntervalUnit::class)],
            'interval_count' => ['nullable', 'integer', 'min:1', 'max:120'],
            'term_months' => ['nullable', 'integer', 'min:1', 'max:240'],
        ]);

        $recurrence = null;

        if ($data['billing_type'] === BillingType::Recurring->value) {
            if (empty($data['interval_unit']) || empty($data['interval_count'])) {
                return response()->json(['message' => 'Falta la frecuencia.'], 422);
            }

            $unit = IntervalUnit::from($data['interval_unit']);
            $months = $unit === IntervalUnit::Year ? $data['interval_count'] * 12 : $data['interval_count'];

            if ($months > Recurrence::MAX_MONTHS) {
                return response()->json(['message' => 'La frecuencia no puede superar 10 años.'], 422);
            }

            $recurrence = Recurrence::of($unit, (int) $data['interval_count']);
        }

        $termMonths = $recurrence && ! empty($data['term_months']) ? (int) $data['term_months'] : null;

        if ($recurrence && $termMonths && ! $calculator->termFitsRecurrence($recurrence, $termMonths)) {
            return response()->json(['message' => "La duración debe ser un número exacto de ciclos ({$recurrence->label()})."], 422);
        }

        return response()->json([
            'label' => $recurrence?->label() ?? BillingType::OneTime->label(),
            ...$schedule->preview(CarbonImmutable::parse($data['start_date']), $recurrence, $termMonths, $clock->today()),
        ]);
    }
}
