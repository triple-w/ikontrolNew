<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-7xl mx-auto">
        <x-ikontrol.page-header
            title="Crear formato"
            description="Define textos, logo y opciones visuales para documentos comerciales."
            :breadcrumbs="['iKontrol', 'Configuracion', 'Formatos de documentos', 'Crear']"
        />

        @include('configuracion.formatos-documentos._form', [
            'action' => route('configuracion.formatos-documentos.store'),
            'method' => 'POST',
        ])
    </div>
</x-app-layout>
