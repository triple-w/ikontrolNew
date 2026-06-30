<x-app-layout>
    <x-ikontrol.page-shell max-width="wide">
        <x-ikontrol.page-header
            title="Remisiones comerciales"
            description="Entrega comercial parcial o total derivada de cotizaciones aceptadas o capturada manualmente."
            :breadcrumbs="['iKontrol', 'Comercial', 'Remisiones']"
            :action-href="route('comercial.remisiones.create')"
            action-label="Nueva remision"
        />

        @if(session('status'))
            <x-ikontrol.info-alert title="Listo">{{ session('status') }}</x-ikontrol.info-alert>
        @endif

        <x-ikontrol.module-section title="Filtros">
            <form method="GET" action="{{ route('comercial.remisiones.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Buscar</label>
                    <input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-md border-gray-300" placeholder="Folio, cliente o cotizacion">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Estado</label>
                    <select name="status" class="w-full rounded-md border-gray-300">
                        <option value="">Todos</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Cliente</label>
                    <select name="commercial_client_id" class="w-full rounded-md border-gray-300">
                        <option value="">Todos</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected((string)($filters['commercial_client_id'] ?? '') === (string)$client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-4 flex flex-wrap gap-2">
                    <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white">Buscar</button>
                    <a href="{{ route('comercial.remisiones.index') }}" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Limpiar</a>
                </div>
            </form>
        </x-ikontrol.module-section>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Folio</th>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Cotizacion</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($remissions as $remission)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $remission->folio }}</td>
                                <td class="px-4 py-3">{{ $remission->commercialClient?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $remission->quote?->folio ?? 'Manual' }}</td>
                                <td class="px-4 py-3 text-right">${{ \App\Support\Decimal::format($remission->total) }}</td>
                                <td class="px-4 py-3"><x-ikontrol.status-badge :tone="$statusTones[$remission->status] ?? 'gray'">{{ $statuses[$remission->status] ?? $remission->status }}</x-ikontrol.status-badge></td>
                                <td class="px-4 py-3">{{ optional($remission->issue_date)->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a class="text-violet-600 hover:underline" href="{{ route('comercial.remisiones.show', $remission) }}">Ver</a>
                                    @if($remission->canBeEdited())
                                        <a class="ml-3 text-violet-600 hover:underline" href="{{ route('comercial.remisiones.edit', $remission) }}">Editar</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10">
                                    <x-ikontrol.empty-state title="Sin remisiones" message="Crea remisiones manuales o desde cotizaciones aceptadas." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $remissions->links() }}</div>
        </div>
    </x-ikontrol.page-shell>
</x-app-layout>
