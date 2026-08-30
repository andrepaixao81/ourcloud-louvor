<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Services\AuditService;
use App\Services\BirthdaySettingsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BirthdayController extends Controller
{
    public function index(Request $request, BirthdaySettingsService $settings)
    {
        $user = $request->user();
        abort_unless($user->canViewBirthdays(), 403);

        $timezone = $user->organization?->timezone ?: config('app.timezone');
        $now = Carbon::now($timezone);
        $requestedMonth = (string) $request->query('month', $now->format('Y-m'));

        try {
            $monthDate = Carbon::createFromFormat('Y-m-d', $requestedMonth.'-01', $timezone)->startOfMonth();
        } catch (\Throwable) {
            $monthDate = $now->copy()->startOfMonth();
        }

        $birthdays = Person::query()
            ->where('status', 'active')
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', $monthDate->month)
            ->orderByRaw('EXTRACT(DAY FROM birth_date)')
            ->orderBy('display_name')
            ->get();

        $upcoming = Person::query()
            ->where('status', 'active')
            ->whereNotNull('birth_date')
            ->get()
            ->map(function (Person $person) use ($now) {
                $birthday = $person->birth_date->copy()->year($now->year);
                if ($birthday->lt($now->copy()->startOfDay())) {
                    $birthday->addYear();
                }
                $person->setAttribute('next_birthday_at', $birthday);
                $person->setAttribute('days_until_birthday', $now->copy()->startOfDay()->diffInDays($birthday->copy()->startOfDay(), false));
                return $person;
            })
            ->filter(fn (Person $person) => (int) $person->days_until_birthday >= 0 && (int) $person->days_until_birthday <= 30)
            ->sortBy('next_birthday_at')
            ->values();

        return view('birthdays.index', [
            'birthdays' => $birthdays,
            'upcoming' => $upcoming,
            'monthDate' => $monthDate,
            'settings' => $settings->get($user->organization_id),
            'canManageSettings' => $user->canManageBirthdaySettings(),
        ]);
    }

    public function updateSettings(Request $request, BirthdaySettingsService $settings, AuditService $audit)
    {
        $user = $request->user();
        abort_unless($user->canManageBirthdaySettings(), 403);

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'send_time' => ['required', 'date_format:H:i'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $settings->save($user->organization_id, [
            'enabled' => $request->boolean('enabled'),
            'send_time' => $data['send_time'],
            'message' => $data['message'],
        ]);

        $audit->event('BirthdaySettings.updated', null, null, [
            'organization_id' => $user->organization_id,
            'enabled' => $request->boolean('enabled'),
            'send_time' => $data['send_time'],
        ]);

        return back()->with('status', 'Configuração de aniversários atualizada.');
    }
}
