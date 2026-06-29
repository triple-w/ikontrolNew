<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-7xl mx-auto">
        <x-ikontrol.page-header
            title="Editar cotizacion"
            :description="$quote->folio"
            :breadcrumbs="['iKontrol', 'Comercial', 'Cotizaciones', $quote->folio, 'Editar']"
        />

        @include('comercial.cotizaciones._form', [
            'action' => route('comercial.cotizaciones.update', $quote),
            'method' => 'PUT',
        ])
    </div>
</x-app-layout>
