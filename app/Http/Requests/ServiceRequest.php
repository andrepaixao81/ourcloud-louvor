<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'service_type_id' => [
                'required',
                'uuid',
                Rule::exists('service_types', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at'),
            ],
            'congregation_id' => [
                'nullable',
                'uuid',
                Rule::exists('congregations', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at'),
            ],
            'location_id' => [
                'nullable',
                'uuid',
                Rule::exists('locations', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at'),
            ],
            'name' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'service_date' => ['required', 'date_format:Y-m-d'],
            'starts_time' => ['required', 'date_format:H:i'],
            'ends_time' => ['nullable', 'date_format:H:i'],
            'status' => ['nullable', Rule::in(['draft', 'scheduled', 'published', 'confirmed', 'in_progress', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:3000'],
            'minister_slots' => ['nullable', 'integer', 'min:1', 'max:12'],
            'manual_minister_person_id' => [
                'nullable',
                'uuid',
                Rule::exists('people', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at'),
            ],
        ];
    }
}
