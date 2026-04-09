<?php

namespace App\Http\Requests\Tenant;

use App\Enums\StudentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorizzazione gestita dal middleware tenant.access
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('students')->where('tenant_id', tenant()->getTenantKey()),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'fiscal_code' => ['nullable', 'string', 'max:16'],
            'address' => ['nullable', 'string', 'max:500'],
            'emergency_contacts' => ['nullable', 'array', 'max:5'],
            'emergency_contacts.*.name' => ['required', 'string', 'max:255'],
            'emergency_contacts.*.phone' => ['required', 'string', 'max:50'],
            'phone_contact_index' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(StudentStatus::class)],
            'enrolled_at' => ['nullable', 'date'],
            'monthly_fee_override' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('monthly_fee_override') && $this->monthly_fee_override !== null && $this->monthly_fee_override !== '') {
            $this->merge([
                'monthly_fee_override' => (int) round($this->monthly_fee_override * 100),
            ]);
        }
    }
}
