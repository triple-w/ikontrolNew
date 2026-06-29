<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-7xl mx-auto">
        <x-ikontrol.page-header
            title="Editar formato"
            :description="$template->name"
            :breadcrumbs="['iKontrol', 'Configuracion', 'Formatos de documentos', $template->name]"
        />

        @include('configuracion.formatos-documentos._form', [
            'action' => route('configuracion.formatos-documentos.update', $template),
            'method' => 'PUT',
        ])
    </div>
</x-app-layout>
