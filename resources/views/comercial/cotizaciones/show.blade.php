<x-app-layout>
    <x-ikontrol.page-shell max-width="wide">
        <x-ikontrol.page-header
            :title="$quote->folio"
            description="Detalle de cotizacion comercial."
            :breadcrumbs="['iKontrol', 'Comercial', 'Cotizaciones', $quote->folio]"
        />

        @if(session('status'))
            <div>
                <x-ikontrol.info-alert title="Listo">{{ session('status') }}</x-ikontrol.info-alert>
            </div>
        @endif

        <div class="flex flex-wrap gap-2">
            @if($quote->canBeEdited())
                <x-ikontrol.primary-link href="{{ route('comercial.cotizaciones.edit', $quote) }}">Editar</x-ikontrol.primary-link>
            @endif
            <x-ikontrol.secondary-link href="{{ route('comercial.cotizaciones.preview', $quote) }}">Previsualizar</x-ikontrol.secondary-link>
            <x-ikontrol.secondary-link href="{{ route('comercial.cotizaciones.pdf', $quote) }}">PDF</x-ikontrol.secondary-link>
            <x-ikontrol.secondary-link href="{{ route('comercial.cotizaciones.print', $quote) }}">Imprimir</x-ikontrol.secondary-link>

            @if($quote->status === \App\Models\CommercialQuote::STATUS_DRAFT)
                <form method="POST" action="{{ route('comercial.cotizaciones.send', $quote) }}">@csrf<button class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Enviar</button></form>
                <form method="POST" action="{{ route('comercial.cotizaciones.cancel', $quote) }}" onsubmit="return confirm('Cancelar esta cotizacion?');">@csrf<button class="rounded-md border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600">Cancelar</button></form>
            @endif

            @if($quote->status === \App\Models\CommercialQuote::STATUS_SENT)
                <form method="POST" action="{{ route('comercial.cotizaciones.accept', $quote) }}">@csrf<button class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Aceptar</button></form>
                <form method="POST" action="{{ route('comercial.cotizaciones.reject', $quote) }}" onsubmit="return confirm('Rechazar esta cotizacion?');">@csrf<button class="rounded-md border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600">Rechazar</button></form>
                <form method="POST" action="{{ route('comercial.cotizaciones.cancel', $quote) }}" onsubmit="return confirm('Cancelar esta cotizacion?');">@csrf<button class="rounded-md border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600">Cancelar</button></form>
            @endif
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 space-y-6">
                <x-ikontrol.module-section title="Resumen">
                    <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div><dt class="text-gray-500">Estado</dt><dd><x-ikontrol.status-badge :tone="$statusTones[$quote->status] ?? 'gray'">{{ $statuses[$quote->status] ?? $quote->status }}</x-ikontrol.status-badge></dd></div>
                        <div><dt class="text-gray-500">Cliente</dt><dd class="font-medium">{{ $quote->commercialClient?->name ?? '-' }}</dd></div>
                        <div><dt class="text-gray-500">Contacto</dt><dd class="font-medium">{{ $quote->commercialContact?->name ?? '-' }}</dd></div>
                        <div><dt class="text-gray-500">Receptor fiscal sugerido</dt><dd class="font-medium">{{ $quote->fiscalClient?->rfc ?? '-' }}</dd></div>
                        <div><dt class="text-gray-500">Emision</dt><dd class="font-medium">{{ optional($quote->issued_at)->format('Y-m-d') }}</dd></div>
                        <div><dt class="text-gray-500">Vencimiento</dt><dd class="font-medium">{{ optional($quote->expires_at)->format('Y-m-d') ?: '-' }}</dd></div>
                        <div><dt class="text-gray-500">Moneda</dt><dd class="font-medium">{{ $quote->currency }}</dd></div>
                        <div><dt class="text-gray-500">Formato comercial</dt><dd class="font-medium">{{ $quote->template_name_snapshot ?: $quote->documentTemplate?->name ?: 'Formato simple' }}</dd></div>
                        <div><dt class="text-gray-500">Creador</dt><dd class="font-medium">{{ $quote->creator?->username ?? '-' }}</dd></div>
                        <div><dt class="text-gray-500">Responsable</dt><dd class="font-medium">{{ $quote->assignedUser?->username ?? '-' }}</dd></div>
                    </dl>
                </x-ikontrol.module-section>

                <x-ikontrol.module-section title="Partidas">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-gray-500">
                                <tr>
                                    <th class="px-3 py-2 text-left">Concepto</th>
                                    <th class="px-3 py-2 text-right">Cantidad</th>
                                    <th class="px-3 py-2 text-right">Precio</th>
                                    <th class="px-3 py-2 text-right">Descuento</th>
                                    <th class="px-3 py-2 text-right">Base</th>
                                    <th class="px-3 py-2 text-right">Impuesto</th>
                                    <th class="px-3 py-2 text-right">Total linea</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($quote->items as $item)
                                    <tr>
                                        <td class="px-3 py-2">
                                            <div class="font-medium">{{ $item->snapshot_name }}</div>
                                            <div class="text-xs text-gray-500">{{ $item->sku ?: '-' }} {{ $item->snapshot_unit ? ' / '.$item->snapshot_unit : '' }}</div>
                                            @if($item->snapshot_description)<div class="mt-1 text-xs text-gray-500">{{ $item->snapshot_description }}</div>@endif
                                        </td>
                                        <td class="px-3 py-2 text-right">{{ \App\Support\Decimal::format($item->quantity, 6) }}</td>
                                        <td class="px-3 py-2 text-right">${{ \App\Support\Decimal::format($item->unit_price) }}</td>
                                        <td class="px-3 py-2 text-right">${{ \App\Support\Decimal::format($item->line_discount_amount) }}</td>
                                        <td class="px-3 py-2 text-right">${{ \App\Support\Decimal::format($item->taxable_base) }}</td>
                                        <td class="px-3 py-2 text-right">${{ \App\Support\Decimal::format($item->tax_amount) }}</td>
                                        <td class="px-3 py-2 text-right">${{ \App\Support\Decimal::format($item->line_total) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ikontrol.module-section>

                <x-ikontrol.module-section title="Condiciones y notas">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div><h3 class="font-medium text-gray-800">Condiciones</h3><p class="mt-2 whitespace-pre-line">{{ $quote->commercial_terms ?: '-' }}</p></div>
                        <div><h3 class="font-medium text-gray-800">Notas para cliente</h3><p class="mt-2 whitespace-pre-line">{{ $quote->customer_notes ?: '-' }}</p></div>
                        <div class="md:col-span-2"><h3 class="font-medium text-gray-800">Notas internas</h3><p class="mt-2 whitespace-pre-line">{{ $quote->internal_notes ?: '-' }}</p></div>
                    </div>
                </x-ikontrol.module-section>

                <x-ikontrol.module-section title="Historial de estado">
                    <div class="space-y-3">
                        @forelse($quote->statusHistory as $row)
                            <div class="rounded-lg border border-gray-200 p-3 text-sm">
                                <div class="font-medium">{{ $statuses[$row->old_status] ?? 'Nuevo' }} -> {{ $statuses[$row->new_status] ?? $row->new_status }}</div>
                                <div class="text-xs text-gray-500">{{ optional($row->changed_at)->format('Y-m-d H:i') }} por {{ $row->user?->username ?? '-' }}</div>
                                @if($row->note)<div class="mt-1 text-gray-600">{{ $row->note }}</div>@endif
                            </div>
                        @empty
                            <x-ikontrol.empty-state title="Sin historial" message="Los cambios de estado apareceran aqui." />
                        @endforelse
                    </div>
                </x-ikontrol.module-section>
            </div>

            <div class="space-y-6">
                <x-ikontrol.module-section title="Totales">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt>Subtotal</dt><dd>${{ \App\Support\Decimal::format($quote->subtotal) }}</dd></div>
                        <div class="flex justify-between"><dt>Descuentos por partida</dt><dd>${{ \App\Support\Decimal::format($quote->line_discount_total) }}</dd></div>
                        <div class="flex justify-between"><dt>Descuento global</dt><dd>${{ \App\Support\Decimal::format($quote->global_discount_amount) }}</dd></div>
                        <div class="flex justify-between"><dt>Descuento total</dt><dd>${{ \App\Support\Decimal::format($quote->discount_total) }}</dd></div>
                        <div class="flex justify-between"><dt>Impuestos</dt><dd>${{ \App\Support\Decimal::format($quote->tax_total) }}</dd></div>
                        <div class="border-t pt-3 flex justify-between text-lg font-semibold text-gray-900"><dt>Total</dt><dd>${{ \App\Support\Decimal::format($quote->total) }}</dd></div>
                    </dl>
                </x-ikontrol.module-section>

                @if($quote->status === \App\Models\CommercialQuote::STATUS_ACCEPTED)
                    <x-ikontrol.module-section title="Remision futura">
                        <div class="space-y-3">
                            <p class="text-sm text-gray-500">Crea una remision parcial o total a partir de esta cotizacion aceptada.</p>
                            <x-ikontrol.primary-link href="{{ route('comercial.cotizaciones.remisiones.create', $quote) }}">Crear remision</x-ikontrol.primary-link>
                        </div>
                    </x-ikontrol.module-section>
                @endif

                <x-ikontrol.module-section title="Comentarios futuros">
                    <x-ikontrol.empty-state title="En preparacion" message="Este espacio queda reservado para seguimiento comercial." />
                </x-ikontrol.module-section>

                <x-ikontrol.module-section title="Archivos futuros">
                    <x-ikontrol.empty-state title="En preparacion" message="Aqui se podran relacionar documentos comerciales." />
                </x-ikontrol.module-section>
            </div>
        </div>
    </x-ikontrol.page-shell>
</x-app-layout>
