<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-6xl mx-auto">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Previsualizar cotizacion</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $document['isTemporary'] ? 'Vista temporal sin guardar, sin folio consumido y sin historial.' : 'Vista de cotizacion guardada.' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $backUrl }}" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Volver</a>
                @if(!$document['isTemporary'])
                    <a href="{{ route('comercial.cotizaciones.pdf', $document['quote']) }}" class="rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white">PDF</a>
                @endif
            </div>
        </div>

        @include('comercial.documentos._invoice', ['document' => $document])
    </div>
</x-app-layout>
