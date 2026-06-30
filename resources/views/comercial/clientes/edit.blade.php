<x-app-layout>
    <x-ikontrol.page-shell>
        <x-ikontrol.page-header
            title="Editar cliente comercial"
            :description="$commercialClient->name"
            :breadcrumbs="['iKontrol', 'Comercial', 'Clientes', 'Editar']"
        />

        @include('comercial.clientes._form', [
            'action' => route('comercial.clientes.update', $commercialClient),
            'method' => 'PUT',
        ])
    </x-ikontrol.page-shell>
</x-app-layout>
