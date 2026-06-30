<x-app-layout>
    <x-ikontrol.page-shell>
        <x-ikontrol.page-header
            title="Nueva remision comercial"
            :description="$quote ? 'Desde cotizacion '.$quote->folio : 'Remision manual sin afectar facturacion fiscal.'"
            :breadcrumbs="$quote ? ['iKontrol', 'Comercial', 'Cotizaciones', $quote->folio, 'Remision'] : ['iKontrol', 'Comercial', 'Remisiones', 'Nueva']"
        />

        @include('comercial.remisiones._form', [
            'action' => $quote ? route('comercial.cotizaciones.remisiones.store', $quote) : route('comercial.remisiones.store'),
            'method' => 'POST',
        ])
    </x-ikontrol.page-shell>
</x-app-layout>
