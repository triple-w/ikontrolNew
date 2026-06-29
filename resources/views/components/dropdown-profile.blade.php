@props([
    'align' => 'right'
])

@php
    $user = Auth::user();
    $roleLabel = trim((string) ($user->rol ?? ''));
    if ($roleLabel === '') {
        $roleLabel = (int) ($user->admin ?? 0) === 1 ? 'Administrador' : 'Usuario';
    }
    $profileUrl = Route::has('profile.show') ? route('profile.show') : route('configuracion.index') . '#cuenta';
@endphp

<div class="relative inline-flex" x-data="{ open: false }">
    <button
        class="inline-flex justify-center items-center group"
        aria-haspopup="true"
        @click.prevent="open = !open"
        :aria-expanded="open"
    >
        <img class="w-8 h-8 rounded-full" src="{{ $user->profile_photo_url }}" width="32" height="32" alt="{{ $user->name }}" />
        <div class="hidden xl:flex items-center truncate">
            <span class="truncate ml-2 text-sm font-medium text-gray-600 dark:text-gray-100 group-hover:text-gray-800 dark:group-hover:text-white">{{ $user->name }}</span>
            <svg class="w-3 h-3 shrink-0 ml-1 fill-current text-gray-400 dark:text-gray-500" viewBox="0 0 12 12">
                <path d="M5.9 11.4.5 6l1.4-1.4 4 4 4-4L11.3 6z" />
            </svg>
        </div>
    </button>
    <div
        class="origin-top-right z-10 absolute top-full min-w-52 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/60 py-1.5 rounded-lg shadow-lg overflow-hidden mt-1 {{$align === 'right' ? 'right-0' : 'left-0'}}"
        @click.outside="open = false"
        @keydown.escape.window="open = false"
        x-show="open"
        x-transition:enter="transition ease-out duration-200 transform"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-out duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
    >
        <div class="pt-0.5 pb-2 px-3 mb-1 border-b border-gray-200 dark:border-gray-700/60">
            <div class="font-medium text-gray-800 dark:text-gray-100">{{ $user->name }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $roleLabel }}</div>
        </div>
        <ul>
            <li>
                <a class="font-medium text-sm text-violet-500 hover:text-violet-600 dark:hover:text-violet-400 flex items-center py-1.5 px-3" href="{{ $profileUrl }}" @click="open = false">Perfil</a>
            </li>
            <li>
                <a class="font-medium text-sm text-violet-500 hover:text-violet-600 dark:hover:text-violet-400 flex items-center py-1.5 px-3" href="{{ route('configuracion.index') }}#cuenta" @click="open = false">Configuracion de cuenta</a>
            </li>
            <li>
                <a class="font-medium text-sm text-violet-500 hover:text-violet-600 dark:hover:text-violet-400 flex items-center py-1.5 px-3" href="{{ route('configuracion.index') }}#rfc" @click="open = false">Empresa y datos fiscales</a>
            </li>
            <li>
                <form method="POST" action="{{ route('logout') }}" x-data>
                    @csrf
                    <a class="font-medium text-sm text-violet-500 hover:text-violet-600 dark:hover:text-violet-400 flex items-center py-1.5 px-3"
                        href="{{ route('logout') }}"
                        @click.prevent="$root.submit();"
                    >
                        Cerrar sesion
                    </a>
                </form>
            </li>
        </ul>
    </div>
</div>
