<x-app-layout>
    <x-ikontrol.page-shell>
        <x-ikontrol.page-header
            title="Crear formato"
            description="Define textos, logo y opciones visuales para documentos comerciales."
            :breadcrumbs="['iKontrol', 'Configuracion', 'Formatos de documentos', 'Crear']"
        />

        @include('configuracion.formatos-documentos._form', [
            'action' => route('configuracion.formatos-documentos.store'),
            'method' => 'POST',
        ])
    </x-ikontrol.page-shell>
</x-app-layout>
