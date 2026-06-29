<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-7xl mx-auto">
        <x-ikontrol.page-header
            title="Editar cliente comercial"
            :description="$commercialClient->name"
            :breadcrumbs="['iKontrol', 'Comercial', 'Clientes', 'Editar']"
        />

        @include('comercial.clientes._form', [
            'action' => route('comercial.clientes.update', $commercialClient),
            'method' => 'PUT',
        ])
    </div>
</x-app-layout>
