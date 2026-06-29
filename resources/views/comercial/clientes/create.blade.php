<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-7xl mx-auto">
        <x-ikontrol.page-header
            title="Nuevo cliente comercial"
            description="Crea un cliente comercial sin modificar el catalogo fiscal."
            :breadcrumbs="['iKontrol', 'Comercial', 'Clientes', 'Nuevo']"
        />

        @include('comercial.clientes._form', [
            'action' => route('comercial.clientes.store'),
            'method' => 'POST',
        ])
    </div>
</x-app-layout>
