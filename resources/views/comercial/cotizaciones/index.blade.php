<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <x-ikontrol.page-header
            title="Cotizaciones comerciales"
            description="Documentos comerciales independientes del flujo CFDI."
            :breadcrumbs="['iKontrol', 'Comercial', 'Cotizaciones']"
            :action-href="route('comercial.cotizaciones.create')"
            action-label="Nueva cotizacion"
        />

        @if(session('status'))
            <div class="mb-6">
                <x-ikontrol.info-alert title="Listo">{{ session('status') }}</x-ikontrol.info-alert>
            </div>
        @endif

        <x-ikontrol.module-section title="Filtros">
            <form method="GET" action="{{ route('comercial.cotizaciones.index') }}" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Buscar</label>
                    <input name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700" placeholder="Folio, cliente, contacto, producto">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Estado</label>
                    <select name="status" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                        <option value="">Todos</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Cliente</label>
                    <select name="commercial_client_id" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                        <option value="">Todos</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected((string)($filters['commercial_client_id'] ?? '') === (string)$client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Responsable</label>
                    <select name="assigned_user_id" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                        <option value="">Todos</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected((string)($filters['assigned_user_id'] ?? '') === (string)$user->id)>{{ $user->username }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Desde</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Hasta</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full rounded-md border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                </div>
                <div class="md:col-span-6 flex flex-wrap gap-2">
                    <button class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white">Buscar</button>
                    <a href="{{ route('comercial.cotizaciones.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700">Limpiar</a>
                </div>
            </form>
        </x-ikontrol.module-section>

        <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-800 shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/30 text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Folio</th>
                            <th class="px-4 py-3 text-left">Cliente</th>
                            <th class="px-4 py-3 text-left">Contacto</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-left">Estado</th>
                            <th class="px-4 py-3 text-left">Fecha</th>
                            <th class="px-4 py-3 text-left">Vencimiento</th>
                            <th class="px-4 py-3 text-left">Responsable</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                        @forelse($quotes as $quote)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $quote->folio }}</td>
                                <td class="px-4 py-3">{{ $quote->commercialClient?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $quote->commercialContact?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">${{ \App\Support\Decimal::format($quote->total) }}</td>
                                <td class="px-4 py-3">{{ $statuses[$quote->status] ?? $quote->status }}</td>
                                <td class="px-4 py-3">{{ optional($quote->issued_at)->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">{{ optional($quote->expires_at)->format('Y-m-d') ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $quote->assignedUser?->username ?? '-' }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a class="text-violet-600 hover:underline" href="{{ route('comercial.cotizaciones.show', $quote) }}">Ver</a>
                                    @if($quote->canBeEdited())
                                        <a class="ml-3 text-violet-600 hover:underline" href="{{ route('comercial.cotizaciones.edit', $quote) }}">Editar</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10">
                                    <x-ikontrol.empty-state title="Sin cotizaciones" message="Crea la primera cotizacion comercial sin afectar el modulo fiscal." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $quotes->links() }}</div>
        </div>
    </div>
</x-app-layout>
