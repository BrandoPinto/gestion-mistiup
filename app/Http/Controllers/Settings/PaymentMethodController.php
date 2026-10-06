<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Shared\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración > Métodos de pago. No se eliminan (los pagos registrados los referencian): se desactivan.
 */
class PaymentMethodController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage-settings');

        return Inertia::render('settings/PaymentMethods', [
            'methods' => PaymentMethod::query()
                ->withCount('payments')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (PaymentMethod $method) => [
                    'id' => $method->id,
                    'name' => $method->name,
                    'is_active' => $method->is_active,
                    'payments_count' => $method->payments_count,
                ]),
        ]);
    }

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate(['name' => ['required', 'string', 'max:60', $this->uniqueName()]], [], ['name' => 'nombre']);

        $method = PaymentMethod::create([
            'code' => $this->uniqueCode($data['name']),
            'name' => $data['name'],
            'is_active' => true,
            'sort_order' => (int) PaymentMethod::max('sort_order') + 1,
        ]);
        $logger->log('settings.payment_method_created', null, ['name' => $method->name]);

        return back()->with('success', "Método «{$method->name}» agregado.");
    }

    public function update(Request $request, PaymentMethod $method, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $request->merge(['name' => trim((string) $request->input('name', $method->name))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', $this->uniqueName($method)],
            'is_active' => ['required', 'boolean'],
        ], [], ['name' => 'nombre']);

        if (! $data['is_active'] && $method->is_active && PaymentMethod::where('is_active', true)->count() <= 1) {
            throw ValidationException::withMessages(['is_active' => 'Debe quedar al menos un método de pago activo.']);
        }

        $method->update($data);
        $logger->log('settings.payment_method_updated', null, ['name' => $method->name, 'is_active' => $method->is_active]);

        return back()->with('success', 'Método de pago actualizado.');
    }

    /** Mueve un método una posición arriba o abajo. */
    public function move(Request $request, PaymentMethod $method): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];
        $ordered = PaymentMethod::query()->orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $ordered->search(fn (PaymentMethod $item) => $item->id === $method->id);
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && $target >= 0 && $target < $ordered->count()) {
            $items = $ordered->all();
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];

            DB::transaction(function () use ($items) {
                foreach ($items as $position => $item) {
                    $item->update(['sort_order' => $position + 1]);
                }
            });
        }

        return back();
    }

    /** Nombre único sin distinguir mayúsculas (mismo resultado en MySQL y SQLite). */
    private function uniqueName(?PaymentMethod $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore) {
            $exists = PaymentMethod::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)])
                ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
                ->exists();

            if ($exists) {
                $fail('Ya existe un método de pago con ese nombre.');
            }
        };
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'metodo';
        $code = $base;
        $suffix = 2;

        while (PaymentMethod::where('code', $code)->exists()) {
            $code = $base.'_'.$suffix++;
        }

        return Str::limit($code, 30, '');
    }
}
