<?php

namespace App\Http\Requests;

use App\Models\CommercialQuoteTax;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommercialRemissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\CommercialRemission::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'commercial_quote_id' => ['nullable', 'integer', Rule::exists('commercial_quotes', 'id')],
            'accept_quote_on_save' => ['nullable', 'boolean'],
            'commercial_client_id' => ['required', 'integer', Rule::exists('commercial_clients', 'id')],
            'commercial_contact_id' => ['nullable', 'integer', Rule::exists('commercial_contacts', 'id')],
            'fiscal_client_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')],
            'commercial_document_template_id' => ['nullable', 'integer', Rule::exists('commercial_document_templates', 'id')],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'issue_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'global_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'conditions' => ['nullable', 'string'],
            'notes_visible' => ['nullable', 'string'],
            'notes_internal' => ['nullable', 'string'],
            'save_action' => ['nullable', Rule::in(['draft', 'issue'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.commercial_quote_item_id' => ['nullable', 'integer', Rule::exists('commercial_quote_items', 'id')],
            'items.*.product_id' => ['nullable', 'integer', Rule::exists('productos', 'id')],
            'items.*.sku' => ['nullable', 'string', 'max:80'],
            'items.*.snapshot_name' => ['required', 'string', 'max:200'],
            'items.*.snapshot_description' => ['nullable', 'string'],
            'items.*.snapshot_unit' => ['nullable', 'string', 'max:80'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.line_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.taxes' => ['nullable', 'array'],
            'items.*.taxes.*.tax_name' => ['nullable', 'string', 'max:80'],
            'items.*.taxes.*.tax_type' => ['nullable', Rule::in(CommercialQuoteTax::TYPES)],
            'items.*.taxes.*.tax_mode' => ['nullable', Rule::in(CommercialQuoteTax::MODES)],
            'items.*.taxes.*.rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.taxes.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
