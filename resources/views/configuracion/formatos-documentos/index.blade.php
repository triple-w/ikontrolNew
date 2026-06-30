<x-app-layout>
    <x-ikontrol.page-shell>
        <x-ikontrol.page-header
            title="Formatos de documentos"
            description="Administra formatos comerciales para cotizaciones, remisiones futuras y otros documentos."
            :breadcrumbs="['iKontrol', 'Configuracion', 'Formatos de documentos']"
            :action-href="route('configuracion.formatos-documentos.create')"
            action-label="Crear formato"
        />

        @if(session('status'))
            <div>
                <x-ikontrol.info-alert title="Listo">{{ session('status') }}</x-ikontrol.info-alert>
            </div>
        @endif

        <x-ikontrol.module-section title="Formatos configurados">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-gray-500">
                        <tr class="border-b border-gray-100">
                            <th class="px-3 py-2">Nombre</th>
                            <th class="px-3 py-2">Tipo</th>
                            <th class="px-3 py-2">Predeterminado</th>
                            <th class="px-3 py-2">Activo</th>
                            <th class="px-3 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($templates as $template)
                            <tr>
                                <td class="px-3 py-3">
                                    <div class="font-medium text-gray-900">{{ $template->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $template->header_title ?: 'Sin titulo de encabezado' }}</div>
                                </td>
                                <td class="px-3 py-3">{{ $types[$template->document_type] ?? $template->document_type }}</td>
                                <td class="px-3 py-3">{{ $template->is_default ? 'Si' : 'No' }}</td>
                                <td class="px-3 py-3">{{ $template->is_active ? 'Si' : 'No' }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        @if(!$template->is_default && $template->is_active)
                                            <form method="POST" action="{{ route('configuracion.formatos-documentos.default', $template) }}">
                                                @csrf
                                                <button class="rounded-md border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700">Predeterminar</button>
                                            </form>
                                        @endif
                                        <a href="{{ route('configuracion.formatos-documentos.edit', $template) }}" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700">Editar</a>
                                        <form method="POST" action="{{ route('configuracion.formatos-documentos.destroy', $template) }}" onsubmit="return confirm('Eliminar este formato? Las cotizaciones historicas conservaran su snapshot.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-md border border-red-200 bg-white px-3 py-2 text-xs font-medium text-red-600">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8">
                                    <x-ikontrol.empty-state title="Sin formatos" message="Crea un formato de cotizacion para usarlo en preview y PDF comercial." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $templates->links() }}
            </div>
        </x-ikontrol.module-section>
    </x-ikontrol.page-shell>
</x-app-layout>
