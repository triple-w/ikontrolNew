<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommercialContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('commercialClient');

        return $client && ($this->user()?->can('update', $client) ?? false);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'position' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'is_primary' => ['nullable', 'boolean'],
            'receives_quotes' => ['nullable', 'boolean'],
            'receives_documents' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
