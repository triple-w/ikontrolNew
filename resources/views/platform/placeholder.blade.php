<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-7xl mx-auto">
        <x-ikontrol.page-header
            :title="$title"
            :description="$description"
            :breadcrumbs="['iKontrol', $area, $title]"
        />

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2">
                <x-ikontrol.module-section title="Modulo en preparacion">
                    <x-ikontrol.empty-state
                        title="Modulo en preparacion"
                        message="Esta pantalla ya forma parte de la navegacion principal de iKontrol, pero todavia no crea registros ni consulta tablas nuevas."
                    />
                </x-ikontrol.module-section>
            </div>

            <div class="space-y-6">
                <x-ikontrol.info-alert title="Objetivo del modulo">
                    {{ $objective }}
                </x-ikontrol.info-alert>

                <x-ikontrol.module-section title="Estado">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Disponibilidad</span>
                        <x-ikontrol.status-badge tone="amber">En preparacion</x-ikontrol.status-badge>
                    </div>
                    <div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                        No requiere tablas nuevas, seeders, migraciones ni datos demo.
                    </div>
                </x-ikontrol.module-section>
            </div>
        </div>
    </div>
</x-app-layout>
