<?php

namespace App\Http\Requests\CommercialDocuments;

use App\Models\CommercialDocumentTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommercialDocumentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CommercialDocumentTemplate::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'document_type' => ['required', Rule::in(CommercialDocumentTemplate::TYPES)],
            'is_default' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
            'header_title' => ['nullable', 'string', 'max:255'],
            'header_text' => ['nullable', 'string'],
            'footer_text' => ['nullable', 'string'],
            'terms_text' => ['nullable', 'string'],
            'accent_style' => ['nullable', Rule::in(['teal', 'violet', 'slate', 'emerald'])],
            'show_logo' => ['nullable', 'boolean'],
            'show_contact_info' => ['nullable', 'boolean'],
            'show_fiscal_info' => ['nullable', 'boolean'],
            'show_item_tax' => ['nullable', 'boolean'],
            'show_item_sku' => ['nullable', 'boolean'],
            'show_notes' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
