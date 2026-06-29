<?php

namespace App\Http\Requests;

use App\Models\CommercialQuoteTax;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommercialQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\CommercialQuote::class) ?? false;
    }

    public function rules(): array
    {
        return $this->baseRules();
    }

    protected function baseRules(): array
    {
        return [
            'commercial_client_id' => ['required', 'integer', Rule::exists('commercial_clients', 'id')],
            'commercial_contact_id' => ['nullable', 'integer', Rule::exists('commercial_contacts', 'id')],
            'fiscal_client_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')],
            'commercial_document_template_id' => ['nullable', 'integer', Rule::exists('commercial_document_templates', 'id')],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'issued_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'currency' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'commercial_terms' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'customer_notes' => ['nullable', 'string'],
            'global_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'save_action' => ['nullable', Rule::in(['draft', 'send'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', Rule::exists('productos', 'id')],
            'items.*.sku' => ['nullable', 'string', 'max:80'],
            'items.*.snapshot_name' => ['required', 'string', 'max:200'],
            'items.*.snapshot_description' => ['nullable', 'string'],
            'items.*.snapshot_unit' => ['nullable', 'string', 'max:80'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.line_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_name' => ['nullable', 'string', 'max:80'],
            'items.*.tax_type' => ['nullable', Rule::in([CommercialQuoteTax::TYPE_TRASLADO, CommercialQuoteTax::TYPE_RETENCION])],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
