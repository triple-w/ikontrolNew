<?php

namespace App\Http\Requests\CommercialDocuments;

class UpdateCommercialDocumentTemplateRequest extends StoreCommercialDocumentTemplateRequest
{
    public function authorize(): bool
    {
        $template = $this->route('template');

        return $template && ($this->user()?->can('update', $template) ?? false);
    }
}
