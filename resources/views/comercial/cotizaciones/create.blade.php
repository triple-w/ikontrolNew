<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-7xl mx-auto">
        <x-ikontrol.page-header
            title="Nueva cotizacion comercial"
            description="Documento comercial no fiscal preparado para futura remision."
            :breadcrumbs="['iKontrol', 'Comercial', 'Cotizaciones', 'Nueva']"
        />

        @include('comercial.cotizaciones._form', [
            'action' => route('comercial.cotizaciones.store'),
            'method' => 'POST',
        ])
    </div>
</x-app-layout>
