@php
    $groups = [
        [
            'label' => 'Inicio',
            'active' => request()->routeIs('dashboard'),
            'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
            ],
        ],
        [
            'label' => 'Comercial',
            'active' => request()->routeIs('comercial.*'),
            'items' => [
                ['label' => 'Clientes', 'route' => 'comercial.clientes.index', 'active' => request()->routeIs('comercial.clientes.*')],
                ['label' => 'Contactos', 'route' => 'comercial.contactos.index', 'active' => request()->routeIs('comercial.contactos.*')],
                ['label' => 'Cotizaciones', 'route' => 'comercial.cotizaciones', 'active' => request()->routeIs('comercial.cotizaciones')],
                ['label' => 'Remisiones', 'route' => 'comercial.remisiones', 'active' => request()->routeIs('comercial.remisiones')],
                ['label' => 'Cuentas por cobrar', 'route' => 'comercial.cuentas-cobrar', 'active' => request()->routeIs('comercial.cuentas-cobrar')],
                ['label' => 'Pagos operativos', 'route' => 'comercial.pagos-operativos', 'active' => request()->routeIs('comercial.pagos-operativos')],
            ],
        ],
        [
            'label' => 'Operacion',
            'active' => request()->routeIs('operacion.*'),
            'items' => [
                ['label' => 'Actividades', 'route' => 'operacion.actividades', 'active' => request()->routeIs('operacion.actividades')],
                ['label' => 'Calendario', 'route' => 'operacion.calendario', 'active' => request()->routeIs('operacion.calendario')],
                ['label' => 'Tareas', 'route' => 'operacion.tareas', 'active' => request()->routeIs('operacion.tareas')],
                ['label' => 'Proyectos', 'route' => 'operacion.proyectos', 'active' => request()->routeIs('operacion.proyectos')],
            ],
        ],
        [
            'label' => 'Fiscal',
            'active' => request()->routeIs('clientes.*') || request()->routeIs('productos.*') || request()->routeIs('folios.*') || request()->routeIs('facturas.*') || request()->routeIs('complementos.*') || request()->routeIs('reportes.*'),
            'items' => [
                ['label' => 'Clientes fiscales', 'route' => 'clientes.index', 'active' => request()->routeIs('clientes.*')],
                ['label' => 'Productos y conceptos', 'route' => 'productos.index', 'active' => request()->routeIs('productos.*')],
                ['label' => 'Series y folios', 'route' => 'folios.index', 'active' => request()->routeIs('folios.*')],
                ['label' => 'Facturas', 'route' => 'facturas.index', 'active' => request()->routeIs('facturas.*')],
                ['label' => 'Complementos de pago', 'route' => 'complementos.index', 'active' => request()->routeIs('complementos.*')],
                ['label' => 'Reportes fiscales', 'route' => 'reportes.index', 'active' => request()->routeIs('reportes.*')],
            ],
        ],
        [
            'label' => 'Configuracion',
            'active' => request()->routeIs('configuracion.*') || request()->routeIs('administracion.*'),
            'items' => [
                ['label' => 'Empresa y datos fiscales', 'route' => 'configuracion.index', 'active' => request()->routeIs('configuracion.*')],
                ['label' => 'Usuarios', 'route' => 'administracion.usuarios', 'active' => request()->routeIs('administracion.usuarios')],
                ['label' => 'Roles y permisos', 'route' => 'administracion.roles', 'active' => request()->routeIs('administracion.roles')],
                ['label' => 'Configuracion general', 'route' => 'administracion.general', 'active' => request()->routeIs('administracion.general')],
            ],
        ],
    ];
@endphp

<div class="min-w-fit">
    <div
        class="fixed inset-0 bg-gray-900/30 z-40 lg:hidden lg:z-auto transition-opacity duration-200"
        :class="sidebarOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'"
        aria-hidden="true"
        x-cloak
    ></div>

    <aside
        id="sidebar"
        class="flex lg:flex! flex-col absolute z-40 left-0 top-0 lg:static lg:left-auto lg:top-auto lg:translate-x-0 h-[100dvh] overflow-y-scroll lg:overflow-y-auto no-scrollbar w-72 lg:w-20 lg:sidebar-expanded:!w-72 2xl:w-72! shrink-0 bg-white dark:bg-gray-800 p-4 transition-all duration-200 ease-in-out {{ $variant === 'v2' ? 'border-r border-gray-200 dark:border-gray-700/60' : 'rounded-r-2xl shadow-xs' }}"
        :class="sidebarOpen ? 'max-lg:translate-x-0' : 'max-lg:-translate-x-72'"
        @click.outside="sidebarOpen = false"
        @keydown.escape.window="sidebarOpen = false"
    >
        <div class="flex items-center justify-between mb-8 pr-3 sm:px-2">
            <a class="flex items-center gap-3 min-w-0" href="{{ route('dashboard') }}">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal-600 text-sm font-bold text-white">iK</span>
                <span class="text-lg font-bold text-gray-800 dark:text-gray-100 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200 truncate">iKontrol</span>
            </a>
            <button class="lg:hidden text-gray-500 hover:text-gray-400" @click.stop="sidebarOpen = !sidebarOpen" aria-controls="sidebar" :aria-expanded="sidebarOpen">
                <span class="sr-only">Cerrar menu</span>
                <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M10.7 18.7l1.4-1.4L7.8 13H20v-2H7.8l4.3-4.3-1.4-1.4L4 12z" />
                </svg>
            </button>
        </div>

        <nav class="space-y-6">
            @foreach($groups as $group)
                <section x-data="{ open: {{ $group['active'] ? 'true' : 'false' }} }">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm font-semibold transition {{ $group['active'] ? 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/40' }}"
                        @click="open = !open; sidebarExpanded = true"
                    >
                        <span class="flex items-center gap-3 min-w-0">
                            <span class="h-2 w-2 rounded-full {{ $group['active'] ? 'bg-violet-500' : 'bg-gray-300 dark:bg-gray-600' }}"></span>
                            <span class="truncate lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">{{ $group['label'] }}</span>
                        </span>
                        <svg class="h-3 w-3 shrink-0 fill-current text-gray-400 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200" :class="open ? 'rotate-180' : 'rotate-0'" viewBox="0 0 12 12" aria-hidden="true">
                            <path d="M5.9 11.4.5 6l1.4-1.4 4 4 4-4L11.3 6z" />
                        </svg>
                    </button>

                    <div class="lg:hidden lg:sidebar-expanded:block 2xl:block">
                        <ul class="mt-2 space-y-1 pl-8" x-show="open">
                            @foreach($group['items'] as $item)
                                <li>
                                    <a
                                        href="{{ route($item['route']) }}"
                                        class="block rounded-md px-3 py-2 text-sm transition {{ $item['active'] ? 'bg-gray-100 font-medium text-gray-900 dark:bg-gray-700/60 dark:text-white' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700/40 dark:hover:text-gray-100' }}"
                                    >
                                        {{ $item['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endforeach
        </nav>

        <div class="pt-3 hidden lg:inline-flex 2xl:hidden justify-end mt-auto">
            <div class="w-12 pl-4 pr-3 py-2">
                <button class="text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 transition-colors" @click="sidebarExpanded = !sidebarExpanded">
                    <span class="sr-only">Expandir o contraer menu</span>
                    <svg class="shrink-0 fill-current text-gray-400 dark:text-gray-500 sidebar-expanded:rotate-180" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M15 16a1 1 0 0 1-1-1V1a1 1 0 1 1 2 0v14a1 1 0 0 1-1 1ZM8.586 7H1a1 1 0 1 0 0 2h7.586l-2.793 2.793a1 1 0 1 0 1.414 1.414l4.5-4.5A.997.997 0 0 0 12 8.01M11.924 7.617a.997.997 0 0 0-.217-.324l-4.5-4.5a1 1 0 0 0-1.414 1.414L8.586 7" />
                    </svg>
                </button>
            </div>
        </div>
    </aside>
</div>
