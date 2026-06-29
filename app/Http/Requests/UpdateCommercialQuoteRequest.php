<?php

namespace App\Http\Requests;

class UpdateCommercialQuoteRequest extends StoreCommercialQuoteRequest
{
    public function authorize(): bool
    {
        $quote = $this->route('commercialQuote');

        return $quote && ($this->user()?->can('update', $quote) ?? false);
    }
}
