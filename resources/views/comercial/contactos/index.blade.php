<x-app-layout>
    <x-ikontrol.page-shell>
        <x-ikontrol.page-header
            title="Contactos comerciales"
            description="Listado global de contactos asociados a clientes comerciales."
            :breadcrumbs="['iKontrol', 'Comercial', 'Contactos']"
        />

        <x-ikontrol.module-section title="Buscar contactos">
            <form method="GET" action="{{ route('comercial.contactos.index') }}" class="flex flex-col sm:flex-row gap-3">
                <input name="q" value="{{ $q }}" class="w-full rounded-md border-gray-300" placeholder="Nombre, correo, telefono o cliente">
                <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white">Buscar</button>
            </form>
        </x-ikontrol.module-section>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Contacto</th>
                            <th class="px-4 py-3 text-left">Cliente</th>
                            <th class="px-4 py-3 text-left">Correo</th>
                            <th class="px-4 py-3 text-left">Telefono</th>
                            <th class="px-4 py-3 text-left">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($contacts as $contact)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $contact->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $contact->position }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <a class="text-violet-600 hover:underline" href="{{ route('comercial.clientes.show', $contact->commercialClient) }}">{{ $contact->commercialClient->name }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $contact->email ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $contact->phone ?: $contact->mobile ?: '-' }}</td>
                                <td class="px-4 py-3"><x-ikontrol.status-badge :tone="$contact->is_active ? 'green' : 'gray'">{{ $contact->is_active ? 'Activo' : 'Inactivo' }}</x-ikontrol.status-badge></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10">
                                    <x-ikontrol.empty-state title="Sin contactos" message="Los contactos se agregan desde el detalle de cada cliente comercial." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $contacts->links() }}</div>
        </div>
    </x-ikontrol.page-shell>
</x-app-layout>
