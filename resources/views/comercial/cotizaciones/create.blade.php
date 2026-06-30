<x-app-layout>
    <x-ikontrol.page-shell>
        <x-ikontrol.page-header
            title="Nueva cotizacion comercial"
            description="Documento comercial no fiscal preparado para futura remision."
            :breadcrumbs="['iKontrol', 'Comercial', 'Cotizaciones', 'Nueva']"
        />

        @include('comercial.cotizaciones._form', [
            'action' => route('comercial.cotizaciones.store'),
            'method' => 'POST',
        ])
    </x-ikontrol.page-shell>
</x-app-layout>
