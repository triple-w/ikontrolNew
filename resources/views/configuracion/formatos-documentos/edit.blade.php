<x-app-layout>
    <x-ikontrol.page-shell>
        <x-ikontrol.page-header
            title="Editar formato"
            :description="$template->name"
            :breadcrumbs="['iKontrol', 'Configuracion', 'Formatos de documentos', $template->name]"
        />

        @include('configuracion.formatos-documentos._form', [
            'action' => route('configuracion.formatos-documentos.update', $template),
            'method' => 'PUT',
        ])
    </x-ikontrol.page-shell>
</x-app-layout>
