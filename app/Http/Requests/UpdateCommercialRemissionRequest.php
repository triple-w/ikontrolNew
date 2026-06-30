<?php

namespace App\Http\Requests;

class UpdateCommercialRemissionRequest extends StoreCommercialRemissionRequest
{
    public function authorize(): bool
    {
        $remission = $this->route('commercialRemission');

        return $remission && ($this->user()?->can('update', $remission) ?? false);
    }
}
