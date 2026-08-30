<?php

namespace App\Http\Controllers;

use App\Models\ActionItem;
use App\Models\Person;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ActionCenterController extends Controller
{
    public function index(Request $request)
    {
        $items = ActionItem::query()->where('user_id', $request->user()->id)
            ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('due_at')->latest()->paginate(30);
        ActionItem::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        $upcomingBirthdays = collect();
        if ($request->user()->canViewBirthdays()) {
            $timezone = $request->user()->organization?->timezone ?: config('app.timezone');
            $today = Carbon::now($timezone)->startOfDay();
            $upcomingBirthdays = Person::query()->where('status', 'active')->whereNotNull('birth_date')->get()
                ->map(function (Person $person) use ($today) {
                    $next = $person->birth_date->copy()->year($today->year);
                    if ($next->lt($today)) $next->addYear();
                    $person->setAttribute('next_birthday_at', $next);
                    $person->setAttribute('days_until_birthday', $today->diffInDays($next, false));
                    return $person;
                })
                ->filter(fn (Person $person) => (int) $person->days_until_birthday >= 0 && (int) $person->days_until_birthday <= 7)
                ->sortBy('next_birthday_at')->values();
        }

        return view('actions.index', compact('items', 'upcomingBirthdays'));
    }

    public function open(Request $request, ActionItem $item)
    {
        abort_unless($item->user_id === $request->user()->id, 403);

        $target = trim((string) $item->action_url);
        if ($target === '') {
            return back()->with('status', 'Este aviso não possui mais um destino disponível.');
        }

        // RC85: itens antigos da Central de Ações podem sobreviver a uma mudança
        // de perfil. Mantemos o contexto no próximo request para que uma eventual
        // resposta 403 explique o motivo e permita dispensar o aviso antigo.
        $request->session()->flash('action_item_context', [
            'id' => $item->id,
            'title' => $item->title,
            'message' => $item->message,
            'from_actions' => true,
        ]);

        if ($item->read_at === null) {
            $item->update(['read_at' => now()]);
        }

        return redirect()->to($target);
    }

    public function complete(Request $request, ActionItem $item)
    {
        abort_unless($item->user_id === $request->user()->id, 403);
        $item->update(['status' => 'completed', 'completed_at' => now(), 'read_at' => $item->read_at ?: now()]);
        return back()->with('status', 'Ação concluída.');
    }

    public function dismiss(Request $request, ActionItem $item)
    {
        abort_unless($item->user_id === $request->user()->id, 403);
        $item->update(['status' => 'dismissed', 'read_at' => $item->read_at ?: now()]);
        return back()->with('status', 'Item dispensado.');
    }
}
