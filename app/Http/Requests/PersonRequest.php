<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'display_name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'birth_date' => ['nullable', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'congregation_id' => [
                'nullable',
                'uuid',
                Rule::exists('congregations', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at'),
            ],
            'joined_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'admin_notes' => ['nullable', 'string', 'max:3000'],
            'ministry_roles' => ['array'],
            'ministry_roles.*' => [
                'uuid',
                Rule::exists('ministry_roles', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at'),
            ],
            'minister_service_types' => ['array'],
            'minister_service_types.*' => [
                'uuid',
                Rule::exists('service_types', 'id')
                    ->where('organization_id', $organizationId)
                    ->where('active', true)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
