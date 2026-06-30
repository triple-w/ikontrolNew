<x-app-layout>
    <x-ikontrol.page-shell max-width="wide">
        <x-ikontrol.page-header
            :title="$remission->folio"
            description="Detalle de remision comercial."
            :breadcrumbs="['iKontrol', 'Comercial', 'Remisiones', $remission->folio]"
        />

        @if(session('status'))
            <x-ikontrol.info-alert title="Listo">{{ session('status') }}</x-ikontrol.info-alert>
        @endif

        <div class="flex flex-wrap gap-2">
            @if($remission->canBeEdited())
                <x-ikontrol.primary-link href="{{ route('comercial.remisiones.edit', $remission) }}">Editar</x-ikontrol.primary-link>
                <form method="POST" action="{{ route('comercial.remisiones.issue', $remission) }}">@csrf<button class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Emitir</button></form>
            @endif
            <x-ikontrol.secondary-link href="{{ route('comercial.remisiones.preview', $remission) }}">Previsualizar</x-ikontrol.secondary-link>
            <x-ikontrol.secondary-link href="{{ route('comercial.remisiones.pdf', $remission) }}">PDF</x-ikontrol.secondary-link>
            @if(in_array($remission->status, [\App\Models\CommercialRemission::STATUS_DRAFT, \App\Models\CommercialRemission::STATUS_ISSUED], true))
                <form method="POST" action="{{ route('comercial.remisiones.cancel', $remission) }}" onsubmit="return confirm('Cancelar esta remision?');">@csrf<button class="rounded-md border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600">Cancelar</button></form>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-ikontrol.module-section title="Resumen">
                    <dl class="grid grid-cols-1 gap-4 text-sm md:grid-cols-3">
                        <div><dt class="text-gray-500">Estado</dt><dd><x-ikontrol.status-badge :tone="$statusTones[$remission->status] ?? 'gray'">{{ $statuses[$remission->status] ?? $remission->status }}</x-ikontrol.status-badge></dd></div>
                        <div><dt class="text-gray-500">Cliente</dt><dd class="font-medium">{{ $remission->commercialClient?->name ?? '-' }}</dd></div>
                        <div><dt class="text-gray-500">Cotizacion origen</dt><dd class="font-medium">{{ $remission->quote?->folio ?? 'Manual' }}</dd></div>
                        <div><dt class="text-gray-500">Fecha</dt><dd class="font-medium">{{ optional($remission->issue_date)->format('Y-m-d') }}</dd></div>
                        <div><dt class="text-gray-500">Moneda</dt><dd class="font-medium">{{ $remission->currency }}</dd></div>
                        <div><dt class="text-gray-500">Responsable</dt><dd class="font-medium">{{ $remission->assignedUser?->username ?? '-' }}</dd></div>
                    </dl>
                </x-ikontrol.module-section>

                <x-ikontrol.module-section title="Partidas">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-gray-500">
                                <tr>
                                    <th class="px-3 py-2">Concepto</th>
                                    <th class="px-3 py-2 text-right">Cantidad</th>
                                    <th class="px-3 py-2 text-right">Precio</th>
                                    <th class="px-3 py-2 text-right">Base</th>
                                    <th class="px-3 py-2 text-right">Impuesto</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($remission->items as $item)
                                    <tr>
                                        <td class="px-3 py-2">
                                            <div class="font-medium">{{ $item->snapshot_name }}</div>
                                            <div class="text-xs text-gray-500">{{ $item->sku ?: '-' }}{{ $item->snapshot_unit ? ' / '.$item->snapshot_unit : '' }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-right">{{ \App\Support\Decimal::format($item->quantity, 6) }}</td>
                                        <td class="px-3 py-2 text-right">${{ \App\Support\Decimal::format($item->unit_price) }}</td>
                                        <td class="px-3 py-2 text-right">${{ \App\Support\Decimal::format($item->taxable_base) }}</td>
                                        <td class="px-3 py-2 text-right">${{ \App\Support\Decimal::format($item->tax_amount) }}</td>
                                        <td class="px-3 py-2 text-right">${{ \App\Support\Decimal::format($item->line_total) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ikontrol.module-section>

                <x-ikontrol.module-section title="Historial">
                    <div class="space-y-3">
                        @forelse($remission->statusHistory as $row)
                            <div class="rounded-lg border border-gray-200 p-3 text-sm">
                                <div class="font-medium">{{ $statuses[$row->old_status] ?? 'Nuevo' }} -> {{ $statuses[$row->new_status] ?? $row->new_status }}</div>
                                <div class="text-xs text-gray-500">{{ optional($row->changed_at)->format('Y-m-d H:i') }} por {{ $row->user?->username ?? '-' }}</div>
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
                        <div class="flex justify-between"><dt>Subtotal</dt><dd>${{ \App\Support\Decimal::format($remission->subtotal) }}</dd></div>
                        <div class="flex justify-between"><dt>Descuentos</dt><dd>${{ \App\Support\Decimal::format($remission->discount_total) }}</dd></div>
                        <div class="flex justify-between"><dt>Traslados</dt><dd>${{ \App\Support\Decimal::format($remission->transfers_total) }}</dd></div>
                        <div class="flex justify-between"><dt>Retenciones</dt><dd>-${{ \App\Support\Decimal::format($remission->withholdings_total) }}</dd></div>
                        <div class="border-t pt-3 flex justify-between text-lg font-semibold text-gray-900"><dt>Total</dt><dd>${{ \App\Support\Decimal::format($remission->total) }}</dd></div>
                    </dl>
                </x-ikontrol.module-section>

                <x-ikontrol.module-section title="Facturacion futura">
                    <div class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500">
                        La conversion manual a factura estara disponible en una etapa posterior.
                    </div>
                </x-ikontrol.module-section>
            </div>
        </div>
    </x-ikontrol.page-shell>
</x-app-layout>
