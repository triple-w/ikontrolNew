<?php

namespace App\Support;

class Decimal
{
    public const SCALE = 6;

    public static function normalize(string|int|null $value, int $scale = self::SCALE): string
    {
        return self::fromScaledInt(self::toScaledInt($value, $scale), $scale);
    }

    public static function add(string|int|null $left, string|int|null $right, int $scale = self::SCALE): string
    {
        return self::fromScaledInt(self::toScaledInt($left, $scale) + self::toScaledInt($right, $scale), $scale);
    }

    public static function sub(string|int|null $left, string|int|null $right, int $scale = self::SCALE): string
    {
        return self::fromScaledInt(self::toScaledInt($left, $scale) - self::toScaledInt($right, $scale), $scale);
    }

    public static function mul(string|int|null $left, string|int|null $right, int $scale = self::SCALE): string
    {
        if (function_exists('bcmul')) {
            return self::round(bcmul(self::normalize($left, $scale), self::normalize($right, $scale), $scale + 4), $scale);
        }

        $leftInt = self::toScaledInt($left, $scale);
        $rightInt = self::toScaledInt($right, $scale);
        $sign = ($leftInt < 0 xor $rightInt < 0) ? -1 : 1;
        $product = abs($leftInt) * abs($rightInt);
        $factor = 10 ** $scale;
        $rounded = intdiv($product + intdiv($factor, 2), $factor);

        return self::fromScaledInt($rounded * $sign, $scale);
    }

    public static function mulDiv(string|int|null $left, string|int|null $right, string|int|null $divisor, int $scale = self::SCALE): string
    {
        if (self::cmp($divisor, '0', $scale) === 0) {
            return self::zero($scale);
        }

        if (function_exists('bcmul') && function_exists('bcdiv')) {
            $product = bcmul(self::normalize($left, $scale), self::normalize($right, $scale), $scale + 6);
            return self::round(bcdiv($product, self::normalize($divisor, $scale), $scale + 6), $scale);
        }

        $leftInt = self::toScaledInt($left, $scale);
        $rightInt = self::toScaledInt($right, $scale);
        $divisorInt = self::toScaledInt($divisor, $scale);

        if ($divisorInt === 0) {
            return self::zero($scale);
        }

        $sign = ($leftInt < 0 xor $rightInt < 0 xor $divisorInt < 0) ? -1 : 1;
        $numerator = abs($leftInt) * abs($rightInt);
        $denominator = abs($divisorInt);
        $rounded = intdiv($numerator + intdiv($denominator, 2), $denominator);

        return self::fromScaledInt($rounded * $sign, $scale);
    }

    public static function max(string|int|null $left, string|int|null $right, int $scale = self::SCALE): string
    {
        return self::cmp($left, $right, $scale) >= 0 ? self::normalize($left, $scale) : self::normalize($right, $scale);
    }

    public static function min(string|int|null $left, string|int|null $right, int $scale = self::SCALE): string
    {
        return self::cmp($left, $right, $scale) <= 0 ? self::normalize($left, $scale) : self::normalize($right, $scale);
    }

    public static function cmp(string|int|null $left, string|int|null $right, int $scale = self::SCALE): int
    {
        return self::toScaledInt($left, $scale) <=> self::toScaledInt($right, $scale);
    }

    public static function allocate(string|int|null $amount, array $bases, int $scale = self::SCALE): array
    {
        $amount = self::normalize($amount, $scale);
        $totalBase = array_reduce($bases, fn (string $carry, $base) => self::add($carry, $base, $scale), self::zero($scale));

        if (self::cmp($amount, '0', $scale) <= 0 || self::cmp($totalBase, '0', $scale) <= 0) {
            return array_fill(0, count($bases), self::zero($scale));
        }

        $remaining = $amount;
        $shares = [];
        $lastIndex = count($bases) - 1;

        foreach (array_values($bases) as $index => $base) {
            $base = self::normalize($base, $scale);
            if ($index === $lastIndex) {
                $share = self::min($remaining, $base, $scale);
            } else {
                $share = self::min(self::mulDiv($amount, $base, $totalBase, $scale), $base, $scale);
                $remaining = self::sub($remaining, $share, $scale);
            }
            $shares[] = $share;
        }

        return $shares;
    }

    public static function round(string|int|null $value, int $scale = self::SCALE): string
    {
        return self::normalize($value, $scale);
    }

    public static function zero(int $scale = self::SCALE): string
    {
        return self::fromScaledInt(0, $scale);
    }

    public static function format(string|int|null $value, int $decimals = 2): string
    {
        $scaled = self::toScaledInt($value, self::SCALE);
        $negative = $scaled < 0;
        $scaled = abs($scaled);
        $factor = 10 ** self::SCALE;
        $whole = intdiv($scaled, $factor);
        $fraction = $scaled % $factor;

        if ($decimals < self::SCALE) {
            $roundFactor = 10 ** (self::SCALE - $decimals);
            $fraction = intdiv($fraction + intdiv($roundFactor, 2), $roundFactor);
            if ($fraction >= (10 ** $decimals)) {
                $whole++;
                $fraction = 0;
            }
        }

        return ($negative ? '-' : '') . $whole . '.' . str_pad((string) $fraction, $decimals, '0', STR_PAD_LEFT);
    }

    public static function toScaledInt(string|int|null $value, int $scale = self::SCALE): int
    {
        $raw = trim(str_replace(',', '', (string) ($value ?? '0')));
        if ($raw === '') {
            $raw = '0';
        }

        $negative = str_starts_with($raw, '-');
        $raw = ltrim($raw, '+-');
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = preg_replace('/\D/', '', $fraction);
        $fraction = str_pad($fraction, $scale + 1, '0');
        $roundDigit = (int) $fraction[$scale];
        $fraction = substr($fraction, 0, $scale);

        $scaled = ((int) $whole * (10 ** $scale)) + (int) $fraction;
        if ($roundDigit >= 5) {
            $scaled++;
        }

        return $negative ? -$scaled : $scaled;
    }

    public static function fromScaledInt(int $value, int $scale = self::SCALE): string
    {
        $negative = $value < 0;
        $value = abs($value);
        $factor = 10 ** $scale;
        $whole = intdiv($value, $factor);
        $fraction = str_pad((string) ($value % $factor), $scale, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '') . $whole . '.' . $fraction;
    }
}
