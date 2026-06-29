<?php

namespace App\Services;

use App\Models\CommercialQuote;
class CommercialQuoteFolioService
{
    public function reserve(int $userId, string $prefix = 'COT'): array
    {
        $prefix = strtoupper(trim($prefix)) ?: 'COT';

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $last = CommercialQuote::query()
                ->where('users_id', $userId)
                ->where('folio_prefix', $prefix)
                ->lockForUpdate()
                ->orderByDesc('folio_number')
                ->first(['folio_number']);

            $number = ((int) ($last?->folio_number ?? 0)) + 1 + $attempt;
            $folio = sprintf('%s-%06d', $prefix, $number);

            if (!CommercialQuote::query()->where('users_id', $userId)->where('folio', $folio)->exists()) {
                return [
                    'folio_prefix' => $prefix,
                    'folio_number' => $number,
                    'folio' => $folio,
                ];
            }
        }

        throw new \RuntimeException('No fue posible reservar un folio comercial unico.');
    }
}
