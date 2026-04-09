<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorizzazione gestita dal middleware tenant.access
    }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string', 'max:1000'],
            'color'              => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'monthly_fee_amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('monthly_fee_amount')) {
            $this->merge([
                'monthly_fee_amount' => (int) round((float) $this->monthly_fee_amount * 100),
            ]);
        }
    }
}
