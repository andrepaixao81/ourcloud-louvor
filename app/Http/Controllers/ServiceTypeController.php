<?php

namespace App\Http\Controllers;

use App\Models\RecurringServiceRule;
use App\Models\MinisterServicePermission;
use App\Models\Service;
use App\Models\ServiceType;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceTypeController extends Controller
{
    public function index()
    {
        return view('service-types.index', [
            'types' => ServiceType::query()->withTrashed()->with(['ministerPermissions' => fn ($q) => $q->where('allowed', true)->with('person')])->orderByRaw('deleted_at nulls first')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:3000'],
            'requires_authorization' => ['nullable', 'boolean'],
            'manual_minister_assignment' => ['nullable', 'boolean'],
        ]);

        $slug = Str::slug($data['name']);
        $exists = ServiceType::query()->withTrashed()->where('slug', $slug)->exists();
        if ($exists) {
            throw ValidationException::withMessages(['name' => 'Já existe um tipo de culto com esse nome, inclusive entre os excluídos.']);
        }

        ServiceType::query()->create($data + [
            'slug' => $slug,
            'organization_id' => $request->user()->organization_id,
            'requires_authorization' => $request->boolean('requires_authorization'),
            'manual_minister_assignment' => $request->boolean('manual_minister_assignment'),
            'active' => true,
        ]);

        return back()->with('status', 'Tipo de culto cadastrado.');
    }

    public function update(Request $request, ServiceType $serviceType, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:3000'],
            'requires_authorization' => ['nullable', 'boolean'],
            'manual_minister_assignment' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);

        $active = $request->boolean('active');
        if (! $active && RecurringServiceRule::query()->where('service_type_id', $serviceType->id)->where('active', true)->exists()) {
            throw ValidationException::withMessages(['active' => 'Este tipo possui recorrência ativa. Desative ou encerre a recorrência antes de inativar o tipo.']);
        }

        $slug = Str::slug($data['name']);
        $duplicate = ServiceType::query()->withTrashed()
            ->where('slug', $slug)
            ->where('id', '<>', $serviceType->id)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'Já existe outro tipo de culto com esse nome.']);
        }

        $wasSpecial = (bool) $serviceType->requires_authorization;
        $requiresAuthorization = $request->boolean('requires_authorization');
        $manualMinisterAssignment = $request->boolean('manual_minister_assignment');

        $serviceType->update([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'requires_authorization' => $requiresAuthorization,
            'manual_minister_assignment' => $manualMinisterAssignment,
            'active' => $active,
        ]);

        $revoked = 0;
        if (! $wasSpecial && $requiresAuthorization) {
            // Ao tornar um tipo especial, as habilitações comuns anteriores não
            // podem virar autorizações especiais silenciosamente.
            $revoked = MinisterServicePermission::query()
                ->where('service_type_id', $serviceType->id)
                ->delete();
        }

        $audit->event('service_type.updated', $serviceType, null, [
            'active' => $active,
            'requires_authorization' => $requiresAuthorization,
            'manual_minister_assignment' => $manualMinisterAssignment,
            'minister_authorizations_revoked' => $revoked,
        ]);

        $message = 'Tipo de culto atualizado.';
        if ($revoked > 0) {
            $message .= " {$revoked} habilitação(ões) de ministro foram removidas porque o tipo passou a exigir autorização específica; revise os ministros autorizados.";
        }

        return back()->with('status', $message);
    }

    public function destroy(Request $request, ServiceType $serviceType, AuditService $audit)
    {
        $data = $request->validate(['reason' => 'required|string|min:3|max:2000']);

        $activeRules = RecurringServiceRule::query()->where('service_type_id', $serviceType->id)->where('active', true)->count();
        $futureServices = Service::query()
            ->where('service_type_id', $serviceType->id)
            ->where('starts_at', '>=', now())
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->count();

        if ($activeRules > 0 || $futureServices > 0) {
            throw ValidationException::withMessages([
                'delete' => "Este tipo ainda está em uso ({$activeRules} recorrência(s) ativa(s), {$futureServices} culto(s) futuro(s)). Ajuste esses vínculos antes de excluí-lo.",
            ]);
        }

        $audit->event('service_type.deleted', $serviceType, $data['reason'], ['name' => $serviceType->name]);
        $serviceType->deleteQuietly();

        return back()->with('status', 'Tipo de culto excluído logicamente.');
    }

    public function restore(Request $request, string $serviceType, AuditService $audit)
    {
        $model = ServiceType::query()->withTrashed()->findOrFail($serviceType);
        abort_unless($model->trashed(), 404);
        $model->restoreQuietly();
        $model->forceFill(['active' => true])->saveQuietly();
        $audit->event('service_type.restored', $model, null, ['name' => $model->name]);

        return back()->with('status', 'Tipo de culto restaurado.');
    }
}
