@php
    $user = auth()->user();
    $roleLabel = trim((string) ($user->rol ?? ''));
    if ($roleLabel === '') {
        $roleLabel = (int) ($user->admin ?? 0) === 1 ? 'Administrador' : 'Usuario';
    }

    $csdTone = $csdHealth['tone'] ?? 'red';
    $csdClasses = match ($csdTone) {
        'green' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300',
        'yellow' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300',
        default => 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300',
    };
@endphp

<header class="sticky top-0 before:absolute before:inset-0 before:backdrop-blur-md max-lg:before:bg-white/90 dark:max-lg:before:bg-gray-800/90 before:-z-10 z-30 {{ $variant === 'v2' || $variant === 'v3' ? 'before:bg-white after:absolute after:h-px after:inset-x-0 after:top-full after:bg-gray-200 dark:after:bg-gray-700/60 after:-z-10' : 'max-lg:shadow-xs lg:before:bg-gray-100/90 dark:lg:before:bg-gray-900/90' }} {{ $variant === 'v2' ? 'dark:before:bg-gray-800' : '' }} {{ $variant === 'v3' ? 'dark:before:bg-gray-900' : '' }}">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 {{ $variant === 'v2' || $variant === 'v3' ? '' : 'lg:border-b border-gray-200 dark:border-gray-700/60' }}">
            <div class="flex items-center gap-3 min-w-0">
                <button
                    class="text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 lg:hidden"
                    @click.stop="sidebarOpen = !sidebarOpen"
                    aria-controls="sidebar"
                    :aria-expanded="sidebarOpen"
                >
                    <span class="sr-only">Abrir menu</span>
                    <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="4" y="5" width="16" height="2" />
                        <rect x="4" y="11" width="16" height="2" />
                        <rect x="4" y="17" width="16" height="2" />
                    </svg>
                </button>

                <div class="hidden md:block min-w-0">
                    <div class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">iKontrol</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate">Centro administrativo</div>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <div class="hidden xl:inline-flex items-center rounded-lg border border-gray-200 dark:border-gray-700/60 px-3 py-1.5">
                    <span class="text-xs text-gray-500 dark:text-gray-400 mr-2">Timbres</span>
                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        {{ (int) ($user->timbres_disponibles ?? 0) }}
                    </span>
                </div>

                <div class="hidden lg:inline-flex items-center rounded-lg border px-3 py-1.5 {{ $csdClasses }}">
                    <span class="text-xs mr-2">Sellos</span>
                    <span class="text-sm font-semibold">{{ $csdHealth['text'] ?? 'Sin sellos' }}</span>
                </div>

                <button type="button" class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:border-gray-300 hover:text-gray-700 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-200" title="Notificaciones">
                    <span class="sr-only">Notificaciones futuras</span>
                    <svg class="h-4 w-4 fill-current" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M8 16a2 2 0 0 0 1.83-1.2H6.17A2 2 0 0 0 8 16Zm6-4H2l1.2-1.6V6a4.8 4.8 0 0 1 9.6 0v4.4L14 12Z" />
                    </svg>
                </button>

                <div class="relative" x-data="{ open: false }">
                    <button type="button" class="inline-flex items-center justify-center rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white" @click="open = !open" :aria-expanded="open">
                        Crear
                    </button>
                    <div class="absolute right-0 z-20 mt-2 w-56 overflow-hidden rounded-lg border border-gray-200 bg-white py-1.5 shadow-lg dark:border-gray-700/60 dark:bg-gray-800" x-show="open" x-transition @click.outside="open = false" x-cloak>
                        <a href="{{ route('facturas.create') }}" class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/60">Crear factura</a>
                        <a href="{{ route('complementos.create') }}" class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/60">Crear complemento</a>
                        <a href="{{ route('clientes.create') }}" class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/60">Crear cliente fiscal</a>
                        <a href="{{ route('comercial.cotizaciones') }}" class="block px-3 py-2 text-sm text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-700/60">Crear cotizacion futura</a>
                    </div>
                </div>

                <div class="hidden sm:block text-right">
                    <div class="max-w-36 truncate text-sm font-medium text-gray-800 dark:text-gray-100">{{ $user->name }}</div>
                    <div class="max-w-36 truncate text-xs text-gray-500 dark:text-gray-400">{{ $roleLabel }}</div>
                </div>

                <x-dropdown-profile align="right" />
            </div>
        </div>
    </div>
</header>
