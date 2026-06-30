<x-app-layout>
    <x-ikontrol.page-shell>
        <x-ikontrol.page-header
            title="Nuevo cliente comercial"
            description="Crea un cliente comercial sin modificar el catalogo fiscal."
            :breadcrumbs="['iKontrol', 'Comercial', 'Clientes', 'Nuevo']"
        />

        @include('comercial.clientes._form', [
            'action' => route('comercial.clientes.store'),
            'method' => 'POST',
        ])
    </x-ikontrol.page-shell>
</x-app-layout>
