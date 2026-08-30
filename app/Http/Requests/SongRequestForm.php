<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SongRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'title' => ['required', 'string', 'max:180'],
            'alternative_title' => ['nullable', 'string', 'max:180'],
            'artist_reference' => ['nullable', 'string', 'max:180'],
            'category_id' => [
                'nullable',
                'uuid',
                Rule::exists('song_categories', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at'),
            ],
            'notes' => ['nullable', 'string', 'max:3000'],
            'version_name' => ['nullable', 'string', 'max:180'],
            'youtube_url' => ['nullable', 'url', 'max:500'],
            'original_key' => ['nullable', 'string', 'max:20'],
            'bpm' => ['nullable', 'integer', 'min:30', 'max:300'],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ];
    }
}
