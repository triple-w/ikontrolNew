<div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
    <h3 class="text-sm font-semibold text-gray-800">Variables disponibles</h3>
    <p class="mt-1 text-xs text-gray-500">Solo estas variables se reemplazan. Cualquier otra expresion queda como texto literal.</p>
    <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
        @foreach($variables as $key => $label)
            <div class="rounded-md bg-white px-3 py-2">
                <code class="text-violet-700">{{ '{{ '.$key.' }}' }}</code>
                <span class="block text-gray-500">{{ $label }}</span>
            </div>
        @endforeach
    </div>
    <div class="mt-3 rounded-md bg-white px-3 py-2 text-xs text-gray-600">
        Ejemplo: <code>Cotizacion para {{ '{{ cliente.nombre }}' }} con fecha {{ '{{ cotizacion.fecha }}' }}</code>
    </div>
</div>
