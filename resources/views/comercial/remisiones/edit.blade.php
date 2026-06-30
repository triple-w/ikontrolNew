<x-app-layout>
    <x-ikontrol.page-shell>
        <x-ikontrol.page-header
            title="Editar remision"
            :description="$remission->folio"
            :breadcrumbs="['iKontrol', 'Comercial', 'Remisiones', $remission->folio, 'Editar']"
        />

        @include('comercial.remisiones._form', [
            'action' => route('comercial.remisiones.update', $remission),
            'method' => 'PUT',
        ])
    </x-ikontrol.page-shell>
</x-app-layout>
