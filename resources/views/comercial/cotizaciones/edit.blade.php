<x-app-layout>
    <x-ikontrol.page-shell>
        <x-ikontrol.page-header
            title="Editar cotizacion"
            :description="$quote->folio"
            :breadcrumbs="['iKontrol', 'Comercial', 'Cotizaciones', $quote->folio, 'Editar']"
        />

        @include('comercial.cotizaciones._form', [
            'action' => route('comercial.cotizaciones.update', $quote),
            'method' => 'PUT',
        ])
    </x-ikontrol.page-shell>
</x-app-layout>
