<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateCommercialClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('commercialClient');

        return $client && ($this->user()?->can('update', $client) ?? false);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'business_name' => ['nullable', 'string', 'max:200'],
            'client_type' => ['required', Rule::in(['person', 'company'])],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'street' => ['nullable', 'string', 'max:120'],
            'exterior_number' => ['nullable', 'string', 'max:30'],
            'interior_number' => ['nullable', 'string', 'max:30'],
            'neighborhood' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'category' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string'],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'is_active' => ['nullable', 'boolean'],
            'fiscal_client_ids' => ['nullable', 'array'],
            'fiscal_client_ids.*' => ['integer', Rule::exists('clientes', 'id')],
            'default_fiscal_client_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')],
            'confirm_without_default' => ['nullable', 'boolean'],
            'duplicate_confirmed' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $client = $this->route('commercialClient');
            $userId = (int) ($client?->users_id ?? $this->user()->id);
            $name = trim((string) $this->input('name'));
            $email = trim((string) $this->input('email'));

            $duplicate = DB::table('commercial_clients')
                ->where('users_id', $userId)
                ->where('id', '!=', $client?->id)
                ->where('name', $name)
                ->when($email !== '', fn ($query) => $query->where('email', $email))
                ->exists();

            if ($duplicate && !$this->boolean('duplicate_confirmed')) {
                $validator->errors()->add('duplicate_confirmed', 'Encontramos un cliente comercial parecido. Confirma que deseas guardar este registro como distinto.');
            }

            $selected = collect($this->input('fiscal_client_ids', []))->map(fn ($id) => (int) $id)->filter()->values();
            $defaultId = (int) $this->input('default_fiscal_client_id', 0);
            if ($defaultId > 0 && !$selected->contains($defaultId)) {
                $validator->errors()->add('default_fiscal_client_id', 'El receptor fiscal predeterminado debe estar seleccionado.');
            }

            if ($selected->isNotEmpty() && $defaultId === 0 && !$this->boolean('confirm_without_default')) {
                $validator->errors()->add('confirm_without_default', 'Elige un receptor fiscal predeterminado o confirma que el cliente quedara sin predeterminado.');
            }
        });
    }
}
