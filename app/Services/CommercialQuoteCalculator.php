<?php

namespace App\Services;

use App\Models\CommercialQuoteTax;
use App\Support\Decimal;
use Illuminate\Validation\ValidationException;

class CommercialQuoteCalculator
{
    public function calculate(array $items, string|int|null $globalDiscountAmount = '0'): array
    {
        $globalDiscountAmount = Decimal::normalize($globalDiscountAmount);
        $prepared = [];
        $subtotal = Decimal::zero();
        $lineDiscountTotal = Decimal::zero();
        $baseBeforeGlobalTotal = Decimal::zero();

        foreach (array_values($items) as $index => $item) {
            $quantity = Decimal::normalize($item['quantity'] ?? '0');
            $unitPrice = Decimal::normalize($item['unit_price'] ?? '0');
            $lineDiscount = Decimal::normalize($item['line_discount_amount'] ?? '0');
            $lineSubtotal = Decimal::mul($quantity, $unitPrice);
            $lineBaseBeforeGlobal = Decimal::max(Decimal::sub($lineSubtotal, $lineDiscount), '0');

            $prepared[] = array_merge($item, [
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_discount_amount' => $lineDiscount,
                'line_subtotal' => $lineSubtotal,
                'line_base_before_global' => $lineBaseBeforeGlobal,
            ]);

            $subtotal = Decimal::add($subtotal, $lineSubtotal);
            $lineDiscountTotal = Decimal::add($lineDiscountTotal, $lineDiscount);
            $baseBeforeGlobalTotal = Decimal::add($baseBeforeGlobalTotal, $lineBaseBeforeGlobal);
        }

        if (empty($prepared)) {
            throw ValidationException::withMessages(['items' => 'Agrega al menos una partida a la cotizacion.']);
        }

        if (Decimal::cmp($globalDiscountAmount, $baseBeforeGlobalTotal) > 0) {
            throw ValidationException::withMessages([
                'global_discount_amount' => 'El descuento global no puede exceder la base comercial despues de descuentos por partida.',
            ]);
        }

        $shares = Decimal::allocate($globalDiscountAmount, array_column($prepared, 'line_base_before_global'));
        $taxTotal = Decimal::zero();
        $taxTransfersTotal = Decimal::zero();
        $taxRetentionsTotal = Decimal::zero();

        foreach ($prepared as $index => $item) {
            $globalShare = $shares[$index] ?? Decimal::zero();
            $taxableBase = Decimal::max(Decimal::sub($item['line_base_before_global'], $globalShare), '0');
            $taxes = [];
            $taxAmount = Decimal::zero();

            foreach ($this->taxPayloads($item) as $taxIndex => $tax) {
                $taxType = in_array($tax['tax_type'], CommercialQuoteTax::TYPES, true)
                    ? $tax['tax_type']
                    : CommercialQuoteTax::TYPE_TRASLADO;
                $taxMode = in_array($tax['tax_mode'], CommercialQuoteTax::MODES, true)
                    ? $tax['tax_mode']
                    : CommercialQuoteTax::MODE_RATE;
                $rate = $taxMode === CommercialQuoteTax::MODE_RATE
                    ? Decimal::normalize($tax['rate'] ?? '0')
                    : Decimal::zero();
                $rawAmount = $taxMode === CommercialQuoteTax::MODE_RATE
                    ? Decimal::mul($taxableBase, $rate)
                    : Decimal::zero();
                $signedAmount = $taxType === CommercialQuoteTax::TYPE_RETENCION
                    ? Decimal::sub('0', $rawAmount)
                    : $rawAmount;

                if ($taxType === CommercialQuoteTax::TYPE_RETENCION) {
                    $taxRetentionsTotal = Decimal::add($taxRetentionsTotal, $rawAmount);
                } else {
                    $taxTransfersTotal = Decimal::add($taxTransfersTotal, $rawAmount);
                }

                $taxAmount = Decimal::add($taxAmount, $signedAmount);
                $taxes[] = [
                    'tax_name' => $tax['tax_name'],
                    'tax_type' => $taxType,
                    'tax_mode' => $taxMode,
                    'rate' => $rate,
                    'base' => $taxableBase,
                    'amount' => $signedAmount,
                    'sort_order' => $taxIndex + 1,
                ];
            }

            $lineTotal = Decimal::add($taxableBase, $taxAmount);

            $prepared[$index] = array_merge($item, [
                'taxes' => $taxes,
                'tax_name' => '',
                'tax_type' => null,
                'tax_rate' => Decimal::zero(),
                'global_discount_share' => $globalShare,
                'taxable_base' => $taxableBase,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
            ]);

            $taxTotal = Decimal::add($taxTotal, $taxAmount);
        }

        $discountTotal = Decimal::add($lineDiscountTotal, $globalDiscountAmount);
        $total = Decimal::add(Decimal::sub($subtotal, $discountTotal), $taxTotal);

        return [
            'items' => $prepared,
            'totals' => [
                'subtotal' => $subtotal,
                'line_discount_total' => $lineDiscountTotal,
                'global_discount_amount' => $globalDiscountAmount,
                'discount_total' => $discountTotal,
                'tax_transfers_total' => $taxTransfersTotal,
                'tax_retentions_total' => $taxRetentionsTotal,
                'tax_total' => $taxTotal,
                'total' => Decimal::max($total, '0'),
            ],
        ];
    }

    private function taxPayloads(array $item): array
    {
        $taxes = collect($item['taxes'] ?? [])
            ->filter(fn ($tax) => is_array($tax) && trim((string) ($tax['tax_name'] ?? '')) !== '')
            ->map(function (array $tax) {
                return [
                    'tax_name' => trim((string) ($tax['tax_name'] ?? '')),
                    'tax_type' => (string) ($tax['tax_type'] ?? CommercialQuoteTax::TYPE_TRASLADO),
                    'tax_mode' => (string) ($tax['tax_mode'] ?? CommercialQuoteTax::MODE_RATE),
                    'rate' => (string) ($tax['rate'] ?? '0'),
                ];
            })
            ->values()
            ->all();

        if (!empty($taxes)) {
            return $taxes;
        }

        $legacyName = trim((string) ($item['tax_name'] ?? ''));
        $legacyRate = Decimal::normalize($item['tax_rate'] ?? '0');
        if ($legacyName === '' && Decimal::cmp($legacyRate, '0') <= 0) {
            return [];
        }

        return [[
            'tax_name' => $legacyName !== '' ? $legacyName : 'IVA',
            'tax_type' => (string) ($item['tax_type'] ?? CommercialQuoteTax::TYPE_TRASLADO),
            'tax_mode' => Decimal::cmp($legacyRate, '0') > 0 ? CommercialQuoteTax::MODE_RATE : CommercialQuoteTax::MODE_ZERO,
            'rate' => $legacyRate,
        ]];
    }
}
