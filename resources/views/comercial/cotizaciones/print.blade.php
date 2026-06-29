<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $quote->folio }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 32px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; font-size: 12px; }
        th { text-align: left; color: #6b7280; }
        .right { text-align: right; }
        .muted { color: #6b7280; }
        .totals { width: 320px; margin-left: auto; margin-top: 24px; }
        @media print { .no-print { display: none; } body { margin: 16px; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Imprimir</button>

    <h1>Cotizacion {{ $quote->folio }}</h1>
    <p class="muted">Documento comercial no fiscal</p>

    <table>
        <tr>
            <td><strong>Cliente:</strong> {{ $quote->commercialClient?->name ?? '-' }}</td>
            <td><strong>Contacto:</strong> {{ $quote->commercialContact?->name ?? '-' }}</td>
            <td><strong>Fecha:</strong> {{ optional($quote->issued_at)->format('Y-m-d') }}</td>
        </tr>
        <tr>
            <td><strong>Receptor sugerido:</strong> {{ $quote->fiscalClient?->rfc ?? '-' }}</td>
            <td><strong>Moneda:</strong> {{ $quote->currency }}</td>
            <td><strong>Vence:</strong> {{ optional($quote->expires_at)->format('Y-m-d') ?: '-' }}</td>
        </tr>
    </table>

    <h2>Partidas</h2>
    <table>
        <thead>
            <tr>
                <th>Concepto</th>
                <th class="right">Cantidad</th>
                <th class="right">Precio</th>
                <th class="right">Descuento</th>
                <th class="right">Impuesto</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->snapshot_name }}</strong><br>
                        <span class="muted">{{ $item->snapshot_description }}</span>
                    </td>
                    <td class="right">{{ \App\Support\Decimal::format($item->quantity, 6) }}</td>
                    <td class="right">${{ \App\Support\Decimal::format($item->unit_price) }}</td>
                    <td class="right">${{ \App\Support\Decimal::format($item->line_discount_amount) }}</td>
                    <td class="right">${{ \App\Support\Decimal::format($item->tax_amount) }}</td>
                    <td class="right">${{ \App\Support\Decimal::format($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">${{ \App\Support\Decimal::format($quote->subtotal) }}</td></tr>
        <tr><td>Descuento total</td><td class="right">${{ \App\Support\Decimal::format($quote->discount_total) }}</td></tr>
        <tr><td>Impuestos</td><td class="right">${{ \App\Support\Decimal::format($quote->tax_total) }}</td></tr>
        <tr><td><strong>Total</strong></td><td class="right"><strong>${{ \App\Support\Decimal::format($quote->total) }}</strong></td></tr>
    </table>

    @if($quote->customer_notes)
        <h2>Notas</h2>
        <p>{{ $quote->customer_notes }}</p>
    @endif
</body>
</html>
