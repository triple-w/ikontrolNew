<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <x-ikontrol.page-header
            title="Clientes comerciales"
            description="Clientes de relacion comercial separados del catalogo fiscal de CFDI."
            :breadcrumbs="['iKontrol', 'Comercial', 'Clientes']"
            :action-href="route('comercial.clientes.create')"
            action-label="Nuevo cliente"
        />

        @if(session('status'))
            <div class="mb-6">
                <x-ikontrol.info-alert title="Listo">{{ session('status') }}</x-ikontrol.info-alert>
            </div>
        @endif

        <x-ikontrol.module-section title="Filtros">
            <form method="GET" action="{{ route('comercial.clientes.index') }}" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Buscar</label>
                    <input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700" placeholder="Nombre, correo, telefono, contacto o RFC">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Estatus</label>
                    <select name="status" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Activos</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactivos</option>
                        <option value="all" @selected(($filters['status'] ?? '') === 'all')>Todos</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Tipo</label>
                    <select name="type" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                        <option value="">Todos</option>
                        <option value="person" @selected(($filters['type'] ?? '') === 'person')>Persona</option>
                        <option value="company" @selected(($filters['type'] ?? '') === 'company')>Empresa</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Responsable</label>
                    <select name="assigned_user_id" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                        <option value="">Todos</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected((string)($filters['assigned_user_id'] ?? '') === (string)$user->id)>{{ $user->username }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Categoria</label>
                    <input name="category" value="{{ $filters['category'] ?? '' }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                </div>
                <div class="md:col-span-6 flex flex-wrap gap-2">
                    <button class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white">Buscar</button>
                    <a href="{{ route('comercial.clientes.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Limpiar</a>
                </div>
            </form>
        </x-ikontrol.module-section>

        <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-800 shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/30 text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Nombre</th>
                            <th class="px-4 py-3 text-left">Tipo</th>
                            <th class="px-4 py-3 text-left">Contacto principal</th>
                            <th class="px-4 py-3 text-left">Telefono / correo</th>
                            <th class="px-4 py-3 text-left">Receptor fiscal</th>
                            <th class="px-4 py-3 text-left">Responsable</th>
                            <th class="px-4 py-3 text-left">Estatus</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                        @forelse($clients as $client)
                            @php
                                $primary = $client->primaryContact->first();
                                $defaultFiscal = $client->defaultFiscalClient->first();
                            @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $client->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $client->category }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $client->client_type === 'person' ? 'Persona' : 'Empresa' }}</td>
                                <td class="px-4 py-3">{{ $primary?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <div>{{ $client->phone ?: $client->mobile ?: '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ $client->email }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($defaultFiscal)
                                        <div class="font-medium">{{ $defaultFiscal->rfc }}</div>
                                        <div class="text-xs text-gray-500">{{ $defaultFiscal->razon_social }}</div>
                                    @else
                                        <span class="text-gray-400">Sin receptor</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $client->assignedUser?->username ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <x-ikontrol.status-badge :tone="$client->is_active ? 'green' : 'gray'">{{ $client->is_active ? 'Activo' : 'Inactivo' }}</x-ikontrol.status-badge>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a class="text-violet-600 hover:underline" href="{{ route('comercial.clientes.show', $client) }}">Ver</a>
                                    <a class="ml-3 text-violet-600 hover:underline" href="{{ route('comercial.clientes.edit', $client) }}">Editar</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10">
                                    <x-ikontrol.empty-state title="Sin clientes comerciales" message="Crea el primer cliente comercial sin afectar tus clientes fiscales." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $clients->links() }}</div>
        </div>
    </div>
</x-app-layout>
