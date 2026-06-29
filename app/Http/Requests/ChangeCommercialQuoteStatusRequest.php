<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeCommercialQuoteStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quote = $this->route('commercialQuote');

        return $quote && ($this->user()?->can('update', $quote) ?? false);
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
