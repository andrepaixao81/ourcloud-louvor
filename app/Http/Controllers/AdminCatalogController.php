<?php

namespace App\Http\Controllers;

use App\Models\MinistryRole;
use App\Models\Person;
use App\Models\Song;
use App\Models\SongCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminCatalogController extends Controller
{
    public function index()
    {
        return view('admin.catalogs', [
            'ministryRoles' => MinistryRole::query()->withTrashed()->orderByRaw('deleted_at is not null')->orderBy('name')->get(),
            'songCategories' => SongCategory::query()->withTrashed()->orderByRaw('deleted_at is not null')->orderBy('name')->get(),
        ]);
    }

    public function storeMinistryRole(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('ministry_roles', 'name')
                    ->where('organization_id', $request->user()->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'is_minister_role' => 'nullable|boolean',
        ]);

        MinistryRole::query()->create([
            'organization_id' => $request->user()->organization_id,
            'name' => trim($data['name']),
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'is_minister_role' => $request->boolean('is_minister_role'),
            'active' => true,
        ]);

        return back()->with('status', 'Função ministerial cadastrada.');
    }

    public function updateMinistryRole(Request $request, MinistryRole $role)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('ministry_roles', 'name')
                    ->ignore($role->id)
                    ->where('organization_id', $request->user()->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'is_minister_role' => 'nullable|boolean',
            'active' => 'nullable|boolean',
        ]);

        $role->update([
            'name' => trim($data['name']),
            'is_minister_role' => $request->boolean('is_minister_role'),
            'active' => $request->boolean('active'),
        ]);

        return back()->with('status', 'Função ministerial atualizada.');
    }

    public function destroyMinistryRole(Request $request, MinistryRole $role)
    {
        $activePeople = Person::query()
            ->where('status', 'active')
            ->whereHas('ministryRoles', fn ($query) => $query->where('ministry_roles.id', $role->id))
            ->exists();

        if ($activePeople) {
            throw ValidationException::withMessages([
                'ministry_role' => 'Esta função ainda está vinculada a pessoas ativas. Remova/reclassifique os vínculos ou apenas desative a função antes de excluí-la.',
            ]);
        }

        $role->update(['active' => false]);
        $role->delete();

        return back()->with('status', 'Função ministerial excluída logicamente. O histórico foi preservado.');
    }

    public function restoreMinistryRole(string $role)
    {
        $item = MinistryRole::query()->withTrashed()->findOrFail($role);
        $item->restore();
        $item->update(['active' => true]);

        return back()->with('status', 'Função ministerial restaurada.');
    }

    public function storeSongCategory(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('song_categories', 'name')
                    ->where('organization_id', $request->user()->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string|max:2000',
        ]);

        SongCategory::query()->create([
            'organization_id' => $request->user()->organization_id,
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'active' => true,
        ]);

        return back()->with('status', 'Categoria de louvor cadastrada.');
    }

    public function updateSongCategory(Request $request, SongCategory $category)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('song_categories', 'name')
                    ->ignore($category->id)
                    ->where('organization_id', $request->user()->organization_id)
                    ->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string|max:2000',
            'active' => 'nullable|boolean',
        ]);

        $category->update([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'active' => $request->boolean('active'),
        ]);

        return back()->with('status', 'Categoria de louvor atualizada.');
    }

    public function destroySongCategory(Request $request, SongCategory $category)
    {
        if (Song::query()->where('category_id', $category->id)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages([
                'song_category' => 'Esta categoria ainda possui louvores ativos. Reclassifique os louvores ou apenas desative a categoria antes de excluí-la.',
            ]);
        }

        $category->update(['active' => false]);
        $category->delete();

        return back()->with('status', 'Categoria excluída logicamente. O histórico foi preservado.');
    }

    public function restoreSongCategory(string $category)
    {
        $item = SongCategory::query()->withTrashed()->findOrFail($category);
        $item->restore();
        $item->update(['active' => true]);

        return back()->with('status', 'Categoria restaurada.');
    }
}
