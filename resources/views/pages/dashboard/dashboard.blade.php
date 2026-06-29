<x-app-layout>
    <div class="mx-auto w-full max-w-9xl min-w-0 px-4 py-8 sm:px-6 lg:px-8">
        @php
            $factucareDashboardPayload = [
                'kpis' => $kpis ?? [],
                'monthlyChart' => $monthlyChart ?? [
                    'labels' => [],
                    'facturas' => [],
                    'complementos' => [],
                    'notas_credito' => [],
                ],
            ];

            $cards = collect($documentCards ?? []);
            $facturasCard = $cards->firstWhere('title', 'Facturas') ?? ['count' => 0, 'amount' => 0];
            $complementosCard = $cards->firstWhere('title', 'Complementos') ?? ['count' => 0, 'amount' => 0];
        @endphp

        <script>
            window.factucareDashboard = @json($factucareDashboardPayload);
        </script>

        <div class="mb-8 flex min-w-0 flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0">
                <x-ikontrol.page-header
                    title="Dashboard iKontrol"
                    description="Centro operativo para fiscal, comercial y operacion. Los modulos futuros ya tienen espacio reservado sin datos demo."
                    :breadcrumbs="['iKontrol', 'Inicio']"
                />
            </div>

            <form method="GET" action="{{ route('dashboard') }}" class="w-full shrink-0 sm:w-auto">
                <select name="range"
                        class="form-select w-full dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-medium sm:w-auto"
                        onchange="this.form.submit()">
                    <option value="month" @selected(($range ?? 'month') === 'month')>Este mes</option>
                    <option value="3m" @selected(($range ?? '') === '3m')>Ultimos 3 meses</option>
                    <option value="6m" @selected(($range ?? '') === '6m')>Ultimo semestre</option>
                    <option value="12m" @selected(($range ?? '') === '12m')>Ultimo ano</option>
                </select>
            </form>
        </div>

        <div class="grid min-w-0 grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
            <div class="min-w-0">
                <x-ikontrol.kpi-card
                    title="Facturas del periodo"
                    :value="number_format((int) ($facturasCard['count'] ?? 0))"
                    :meta="'Total fiscal: $' . number_format((float) ($facturasCard['amount'] ?? 0), 2)"
                    tone="violet"
                >
                    <span class="text-sm font-bold">F</span>
                </x-ikontrol.kpi-card>
            </div>

            <div class="min-w-0">
                <x-ikontrol.kpi-card
                    title="Complementos del periodo"
                    :value="number_format((int) ($complementosCard['count'] ?? 0))"
                    :meta="'Total pagos: $' . number_format((float) ($complementosCard['amount'] ?? 0), 2)"
                    tone="sky"
                >
                    <span class="text-sm font-bold">P</span>
                </x-ikontrol.kpi-card>
            </div>

            <div class="min-w-0">
                <x-ikontrol.kpi-card
                    title="Clientes fiscales"
                    :value="number_format((int) ($clientesFiscales ?? 0))"
                    meta="Registros asociados al usuario actual"
                    tone="emerald"
                >
                    <span class="text-sm font-bold">C</span>
                </x-ikontrol.kpi-card>
            </div>

            <div class="min-w-0">
                <x-ikontrol.kpi-card
                    title="Pendientes operativos"
                    value="0"
                    meta="Sin modulos operativos configurados"
                    tone="amber"
                >
                    <span class="text-sm font-bold">O</span>
                </x-ikontrol.kpi-card>
            </div>
        </div>

        <div class="mt-6 grid min-w-0 grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="min-w-0 space-y-6 xl:col-span-2">
                <x-ikontrol.module-section title="Documentos fiscales" description="Resumen del periodo seleccionado con los calculos existentes del dashboard.">
                    <div class="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-3">
                        @foreach(($kpis ?? []) as $label => $kpi)
                            <div class="min-w-0 rounded-lg border border-gray-200 p-4 dark:border-gray-700/60">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ ucfirst($label) }}</div>
                                <div class="mt-2 break-words text-2xl font-bold text-gray-800 dark:text-gray-100">${{ number_format((float) ($kpi['actual'] ?? 0), 2) }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Periodo anterior: ${{ number_format((float) ($kpi['previo'] ?? 0), 2) }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-ikontrol.module-section>

                <x-dashboard.dashboard-card-15 :monthlyChart="$monthlyChart" />
            </div>

            <div class="min-w-0 space-y-6">
                <x-ikontrol.module-section title="Accesos rapidos">
                    <div class="grid grid-cols-1 gap-3">
                        <x-ikontrol.primary-link href="{{ route('facturas.create') }}">Crear factura</x-ikontrol.primary-link>
                        <x-ikontrol.secondary-link href="{{ route('complementos.create') }}">Crear complemento</x-ikontrol.secondary-link>
                        <x-ikontrol.secondary-link href="{{ route('clientes.create') }}">Crear cliente fiscal</x-ikontrol.secondary-link>
                        <x-ikontrol.secondary-link href="{{ route('productos.create') }}">Crear producto</x-ikontrol.secondary-link>
                        <x-ikontrol.secondary-link href="{{ route('comercial.cotizaciones.create') }}">Crear cotización</x-ikontrol.secondary-link>
                        <x-ikontrol.secondary-link href="{{ route('operacion.actividades') }}">Crear actividad futura</x-ikontrol.secondary-link>
                    </div>
                </x-ikontrol.module-section>

                <x-ikontrol.module-section title="Proximas actividades">
                    <x-ikontrol.empty-state
                        title="Sin actividades programadas"
                        message="El modulo de actividades todavia no esta configurado."
                    />
                </x-ikontrol.module-section>

                <x-ikontrol.module-section title="Tareas pendientes">
                    <x-ikontrol.empty-state
                        title="Sin tareas pendientes"
                        message="El tablero de tareas se agregara cuando exista el modulo operativo."
                    />
                </x-ikontrol.module-section>
            </div>
        </div>

        <div class="mt-6">
            <x-ikontrol.module-section title="Pendientes operativos">
                <x-ikontrol.empty-table
                    :columns="['Modulo', 'Responsable', 'Estado']"
                    message="Sin modulos operativos configurados."
                />
            </x-ikontrol.module-section>
        </div>
    </div>
</x-app-layout>
