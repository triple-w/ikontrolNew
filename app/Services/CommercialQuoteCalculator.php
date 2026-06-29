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

        foreach ($prepared as $index => $item) {
            $taxName = trim((string) ($item['tax_name'] ?? ''));
            $taxType = (string) ($item['tax_type'] ?? CommercialQuoteTax::TYPE_TRASLADO);
            $taxRate = Decimal::normalize($item['tax_rate'] ?? '0');
            $globalShare = $shares[$index] ?? Decimal::zero();
            $taxableBase = Decimal::max(Decimal::sub($item['line_base_before_global'], $globalShare), '0');
            $rawTaxAmount = Decimal::mul($taxableBase, $taxRate);
            $taxAmount = $taxType === CommercialQuoteTax::TYPE_RETENCION
                ? Decimal::sub('0', $rawTaxAmount)
                : $rawTaxAmount;
            $lineTotal = Decimal::add($taxableBase, $taxAmount);

            $prepared[$index] = array_merge($item, [
                'tax_name' => $taxName !== '' ? $taxName : (Decimal::cmp($taxRate, '0') > 0 ? 'IVA' : ''),
                'tax_type' => in_array($taxType, [CommercialQuoteTax::TYPE_TRASLADO, CommercialQuoteTax::TYPE_RETENCION], true)
                    ? $taxType
                    : CommercialQuoteTax::TYPE_TRASLADO,
                'tax_rate' => $taxRate,
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
                'tax_total' => $taxTotal,
                'total' => Decimal::max($total, '0'),
            ],
        ];
    }
}
