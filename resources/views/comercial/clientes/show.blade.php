<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <x-ikontrol.page-header
            :title="$commercialClient->name"
            description="Detalle comercial, contactos y receptores fiscales relacionados."
            :breadcrumbs="['iKontrol', 'Comercial', 'Clientes', $commercialClient->name]"
            :action-href="route('comercial.clientes.edit', $commercialClient)"
            action-label="Editar cliente"
        />

        @if(session('status'))
            <div class="mb-6">
                <x-ikontrol.info-alert title="Listo">{{ session('status') }}</x-ikontrol.info-alert>
            </div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 space-y-6">
                <x-ikontrol.module-section title="Resumen">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-gray-500">Tipo</dt><dd class="font-medium">{{ $commercialClient->client_type === 'person' ? 'Persona' : 'Empresa' }}</dd></div>
                        <div><dt class="text-gray-500">Estatus</dt><dd><x-ikontrol.status-badge :tone="$commercialClient->is_active ? 'green' : 'gray'">{{ $commercialClient->is_active ? 'Activo' : 'Inactivo' }}</x-ikontrol.status-badge></dd></div>
                        <div><dt class="text-gray-500">Correo</dt><dd class="font-medium">{{ $commercialClient->email ?: '-' }}</dd></div>
                        <div><dt class="text-gray-500">Telefono</dt><dd class="font-medium">{{ $commercialClient->phone ?: $commercialClient->mobile ?: '-' }}</dd></div>
                        <div><dt class="text-gray-500">Responsable</dt><dd class="font-medium">{{ $commercialClient->assignedUser?->username ?? '-' }}</dd></div>
                        <div><dt class="text-gray-500">Categoria</dt><dd class="font-medium">{{ $commercialClient->category ?: '-' }}</dd></div>
                    </dl>
                    @if($commercialClient->notes)
                        <div class="mt-5 rounded-lg bg-gray-50 dark:bg-gray-900/30 p-4 text-sm">{{ $commercialClient->notes }}</div>
                    @endif
                </x-ikontrol.module-section>

                <x-ikontrol.module-section title="Contactos">
                    <form method="POST" action="{{ route('comercial.contactos.store', $commercialClient) }}" class="mb-6 grid grid-cols-1 md:grid-cols-6 gap-3">
                        @csrf
                        <input name="name" class="rounded-md border-gray-300 md:col-span-2" placeholder="Nombre" required>
                        <input name="position" class="rounded-md border-gray-300" placeholder="Puesto">
                        <input type="email" name="email" class="rounded-md border-gray-300" placeholder="Correo">
                        <input name="phone" class="rounded-md border-gray-300" placeholder="Telefono">
                        <input name="mobile" class="rounded-md border-gray-300" placeholder="Celular">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_primary" value="1" class="rounded"> Principal</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="receives_quotes" value="1" checked class="rounded"> Cotizaciones</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="receives_documents" value="1" class="rounded"> Documentos</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked class="rounded"> Activo</label>
                        <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white md:col-span-2">Agregar contacto</button>
                    </form>

                    @if($commercialClient->contacts->isEmpty())
                        <x-ikontrol.empty-state title="Sin contactos" message="Agrega contactos comerciales para cotizaciones y seguimiento." />
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Nombre</th>
                                        <th class="px-3 py-2 text-left">Correo</th>
                                        <th class="px-3 py-2 text-left">Telefono</th>
                                        <th class="px-3 py-2 text-left">Permisos</th>
                                        <th class="px-3 py-2 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($commercialClient->contacts as $contact)
                                        <tr>
                                            <td class="px-3 py-2">
                                                <div class="font-medium">{{ $contact->name }}</div>
                                                <div class="text-xs text-gray-500">{{ $contact->position }}</div>
                                            </td>
                                            <td class="px-3 py-2">{{ $contact->email ?: '-' }}</td>
                                            <td class="px-3 py-2">{{ $contact->phone ?: $contact->mobile ?: '-' }}</td>
                                            <td class="px-3 py-2">
                                                <div class="flex flex-wrap gap-1">
                                                    @if($contact->is_primary)<x-ikontrol.status-badge tone="green">Principal</x-ikontrol.status-badge>@endif
                                                    @if($contact->receives_quotes)<x-ikontrol.status-badge tone="sky">Cotiza</x-ikontrol.status-badge>@endif
                                                    @if($contact->receives_documents)<x-ikontrol.status-badge tone="amber">Docs</x-ikontrol.status-badge>@endif
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                                <a class="text-violet-600 hover:underline" href="{{ route('comercial.contactos.edit', [$commercialClient, $contact]) }}">Editar</a>
                                                <form method="POST" action="{{ route('comercial.contactos.destroy', [$commercialClient, $contact]) }}" class="inline" onsubmit="return confirm('Eliminar contacto?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="ml-3 text-red-600 hover:underline">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-ikontrol.module-section>
            </div>

            <div class="space-y-6">
                <x-ikontrol.module-section title="Receptores fiscales">
                    @if($commercialClient->fiscalClients->isEmpty())
                        <x-ikontrol.empty-state title="Sin receptores fiscales" message="Este cliente comercial puede guardarse sin datos fiscales." />
                    @else
                        <div class="space-y-3">
                            @foreach($commercialClient->fiscalClients as $fiscal)
                                <div class="rounded-lg border border-gray-200 p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="font-medium">{{ $fiscal->razon_social }}</div>
                                            <div class="text-xs text-gray-500">{{ $fiscal->rfc }}</div>
                                        </div>
                                        @if((bool)$fiscal->pivot->is_default)
                                            <x-ikontrol.status-badge tone="green">Default</x-ikontrol.status-badge>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-ikontrol.module-section>

                @foreach(['Actividad futura', 'Cotizaciones futuras', 'Remisiones futuras', 'Cuenta futura', 'Archivos futuros'] as $future)
                    <x-ikontrol.module-section :title="$future">
                        <x-ikontrol.empty-state title="Modulo en preparacion" message="Este bloque quedo reservado para el crecimiento comercial." />
                    </x-ikontrol.module-section>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
