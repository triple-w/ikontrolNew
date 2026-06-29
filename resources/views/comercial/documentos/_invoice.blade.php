@php
    use App\Support\Decimal;

    $quote = $document['quote'];
    $template = $document['template'];
    $totals = $document['totals'];
    $accent = [
        'teal' => '#0e948e',
        'violet' => '#7c3aed',
        'slate' => '#334155',
        'emerald' => '#059669',
    ][$template['accent_style'] ?? 'teal'] ?? '#0e948e';
@endphp

<style>
    .commercial-invoice { --accent: {{ $accent }}; color: #111827; font-family: Arial, sans-serif; }
    .commercial-invoice .doc-shell { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
    .commercial-invoice .doc-header { border-top: 6px solid var(--accent); padding: 28px; display: grid; grid-template-columns: 1fr auto; gap: 24px; }
    .commercial-invoice .doc-title { font-size: 26px; font-weight: 700; margin: 0; }
    .commercial-invoice .muted { color: #6b7280; }
    .commercial-invoice .plain { white-space: pre-line; }
    .commercial-invoice .logo { max-width: 150px; max-height: 90px; object-fit: contain; }
    .commercial-invoice .section { padding: 0 28px 24px; }
    .commercial-invoice .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
    .commercial-invoice .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; }
    .commercial-invoice .label { color: #6b7280; font-size: 12px; text-transform: uppercase; }
    .commercial-invoice table { width: 100%; border-collapse: collapse; }
    .commercial-invoice th { color: #6b7280; font-size: 11px; text-transform: uppercase; text-align: left; border-bottom: 1px solid #e5e7eb; padding: 10px 8px; }
    .commercial-invoice td { border-bottom: 1px solid #f3f4f6; padding: 10px 8px; vertical-align: top; font-size: 13px; }
    .commercial-invoice .right { text-align: right; }
    .commercial-invoice .totals { max-width: 360px; margin-left: auto; }
    .commercial-invoice .total-row { border-top: 2px solid #111827; font-size: 18px; font-weight: 700; }
    .commercial-invoice .badge { display: inline-block; border: 1px solid #d1d5db; border-radius: 999px; padding: 4px 10px; font-size: 12px; color: #374151; }
    @media (max-width: 760px) {
        .commercial-invoice .doc-header { grid-template-columns: 1fr; }
        .commercial-invoice .grid { grid-template-columns: 1fr; }
    }
    @media print {
        .commercial-invoice .doc-shell { border: 0; border-radius: 0; }
    }
</style>

<div class="commercial-invoice">
    <div class="doc-shell">
        <div class="doc-header">
            <div>
                <p class="muted" style="margin:0 0 6px;">Documento comercial no fiscal</p>
                <h1 class="doc-title">{{ $document['resolved']['header_title'] ?: 'Cotizacion comercial' }}</h1>
                <p class="plain muted" style="margin:12px 0 0;">{{ $document['resolved']['header_text'] }}</p>
            </div>
            <div class="right">
                @if(($template['show_logo'] ?? true) && !empty($document['logoDataUri']))
                    <img src="{{ $document['logoDataUri'] }}" alt="Logo" class="logo">
                @endif
                <div style="margin-top:12px;">
                    <div class="label">Folio</div>
                    <div style="font-size:20px;font-weight:700;">{{ $quote->folio ?: 'Borrador' }}</div>
                </div>
                <div class="muted" style="margin-top:8px;font-size:13px;">
                    {{ optional($quote->issued_at)->format('Y-m-d') ?: $quote->issued_at }}
                    @if($quote->expires_at)
                        <br>Vence: {{ optional($quote->expires_at)->format('Y-m-d') ?: $quote->expires_at }}
                    @endif
                </div>
            </div>
        </div>

        <div class="section">
            <div class="grid">
                <div class="box">
                    <div class="label">Empresa</div>
                    <div style="font-weight:700;">{{ $document['company']['nombre'] ?: 'Empresa' }}</div>
                    @if($document['company']['rfc'])
                        <div class="muted">RFC: {{ $document['company']['rfc'] }}</div>
                    @endif
                    @if($document['company']['telefono'])
                        <div class="muted">Tel: {{ $document['company']['telefono'] }}</div>
                    @endif
                    @if($document['company']['email'])
                        <div class="muted">{{ $document['company']['email'] }}</div>
                    @endif
                    @if($document['company']['direccion'])
                        <div class="plain muted">{{ $document['company']['direccion'] }}</div>
                    @endif
                </div>

                <div class="box">
                    <div class="label">Cliente</div>
                    <div style="font-weight:700;">{{ $document['client']?->name ?? '-' }}</div>
                    @if($document['client']?->business_name)
                        <div class="muted">{{ $document['client']->business_name }}</div>
                    @endif
                    @if($template['show_contact_info'] ?? true)
                        @if($document['contact'])
                            <div class="muted" style="margin-top:8px;">Contacto: {{ $document['contact']->name }}</div>
                            @if($document['contact']->email)<div class="muted">{{ $document['contact']->email }}</div>@endif
                            @if($document['contact']->phone || $document['contact']->mobile)<div class="muted">{{ $document['contact']->phone ?: $document['contact']->mobile }}</div>@endif
                        @endif
                    @endif
                    @if(($template['show_fiscal_info'] ?? false) && $document['fiscalClient'])
                        <div class="muted" style="margin-top:8px;">Receptor sugerido: {{ $document['fiscalClient']->rfc }} {{ $document['fiscalClient']->razon_social }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="section">
            <table>
                <thead>
                    <tr>
                        <th>Concepto</th>
                        @if($template['show_item_sku'] ?? true)<th>SKU</th>@endif
                        <th class="right">Cant.</th>
                        <th>Unidad</th>
                        <th class="right">Precio</th>
                        <th class="right">Desc.</th>
                        @if($template['show_item_tax'] ?? true)<th class="right">Impuesto</th>@endif
                        <th class="right">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($document['items'] as $item)
                        <tr>
                            <td>
                                <strong>{{ $item['name'] }}</strong>
                                @if($item['description'])
                                    <div class="plain muted">{{ $item['description'] }}</div>
                                @endif
                                @if(($template['show_notes'] ?? true) && !empty($item['notes']))
                                    <div class="plain muted">Nota: {{ $item['notes'] }}</div>
                                @endif
                            </td>
                            @if($template['show_item_sku'] ?? true)<td>{{ $item['sku'] ?: '-' }}</td>@endif
                            <td class="right">{{ Decimal::format($item['quantity'], 6) }}</td>
                            <td>{{ $item['unit'] ?: '-' }}</td>
                            <td class="right">${{ Decimal::format($item['unit_price']) }}</td>
                            <td class="right">${{ Decimal::format($item['discount']) }}</td>
                            @if($template['show_item_tax'] ?? true)
                                <td class="right">
                                    {{ $item['tax_name'] ?: '-' }}
                                    @if(!empty($item['tax_rate']) && Decimal::cmp($item['tax_rate'], '0') > 0)
                                        <br><span class="muted">${{ Decimal::format($item['tax_amount']) }}</span>
                                    @endif
                                </td>
                            @endif
                            <td class="right">${{ Decimal::format($item['line_total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="totals">
                <table>
                    <tr><td>Subtotal</td><td class="right">${{ Decimal::format($totals['subtotal'] ?? '0') }}</td></tr>
                    <tr><td>Descuentos por partida</td><td class="right">${{ Decimal::format($totals['line_discount_total'] ?? '0') }}</td></tr>
                    <tr><td>Descuento global</td><td class="right">${{ Decimal::format($totals['global_discount_amount'] ?? '0') }}</td></tr>
                    <tr><td>Impuestos</td><td class="right">${{ Decimal::format($totals['tax_total'] ?? '0') }}</td></tr>
                    <tr class="total-row"><td>Total {{ $quote->currency ?: 'MXN' }}</td><td class="right">${{ Decimal::format($totals['total'] ?? '0') }}</td></tr>
                </table>
            </div>
        </div>

        @if(($template['show_notes'] ?? true) && $quote->customer_notes)
            <div class="section">
                <div class="box">
                    <div class="label">Notas para cliente</div>
                    <div class="plain">{{ $quote->customer_notes }}</div>
                </div>
            </div>
        @endif

        @if($quote->commercial_terms || $document['resolved']['terms_text'])
            <div class="section">
                <div class="box">
                    <div class="label">Condiciones</div>
                    <div class="plain">{{ trim(($quote->commercial_terms ?: '') . "\n" . ($document['resolved']['terms_text'] ?: '')) }}</div>
                </div>
            </div>
        @endif

        @if($document['resolved']['footer_text'])
            <div class="section">
                <div class="plain muted" style="border-top:1px solid #e5e7eb;padding-top:16px;">{{ $document['resolved']['footer_text'] }}</div>
            </div>
        @endif
    </div>
</div>
