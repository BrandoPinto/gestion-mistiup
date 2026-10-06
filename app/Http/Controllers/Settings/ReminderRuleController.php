<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Reminders\Enums\ReminderEvent;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\ReminderRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración > Recordatorios: qué avisos se emiten y con cuántos días.
 */
class ReminderRuleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage-settings');

        return Inertia::render('settings/Reminders', [
            'rules' => ReminderRule::query()->orderBy('event')->orderBy('days')->get()->map(fn (ReminderRule $rule) => [
                'id' => $rule->id,
                'event' => $rule->event->value,
                'days' => $rule->days,
                'label' => $rule->event->daysLabel($rule->days),
                'is_active' => $rule->is_active,
            ]),
            'events' => collect(ReminderEvent::cases())->map(fn (ReminderEvent $event) => ['value' => $event->value, 'label' => $event->label()]),
        ]);
    }

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $data = $request->validate([
            'event' => ['required', Rule::enum(ReminderEvent::class)],
            'days' => ['required', 'integer', 'min:0', 'max:365', Rule::unique('reminder_rules')->where('event', $request->input('event'))],
        ], [
            'days.unique' => 'Ya existe un recordatorio con esos días para ese tipo de aviso.',
        ], ['days' => 'días']);

        $rule = ReminderRule::create([...$data, 'is_active' => true]);
        $logger->log('settings.reminder_rule_created', null, ['event' => $rule->event->value, 'days' => $rule->days]);

        return back()->with('success', 'Recordatorio agregado.');
    }

    public function update(Request $request, ReminderRule $rule, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $rule->update($data);
        $logger->log('settings.reminder_rule_updated', null, ['event' => $rule->event->value, 'days' => $rule->days, 'is_active' => $rule->is_active]);

        return back()->with('success', $rule->is_active ? 'Recordatorio activado.' : 'Recordatorio pausado.');
    }

    public function destroy(ReminderRule $rule, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $logger->log('settings.reminder_rule_deleted', null, ['event' => $rule->event->value, 'days' => $rule->days]);
        $rule->delete();

        return back()->with('success', 'Recordatorio eliminado.');
    }
}
