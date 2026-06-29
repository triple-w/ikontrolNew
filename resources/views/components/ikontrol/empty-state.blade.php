@props([
    'title' => 'Sin registros',
    'message' => 'Todavia no hay informacion disponible.',
])

<div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-700/60 bg-gray-50 dark:bg-gray-900/30 px-6 py-10 text-center">
    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/60">
        <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M4 4a2 2 0 0 1 2-2h5.2c.53 0 1.04.21 1.41.59l2.8 2.8c.38.37.59.88.59 1.41V16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4Zm8 1V3.5L14.5 6H13a1 1 0 0 1-1-1Z" />
        </svg>
    </div>
    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $title }}</h3>
    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $message }}</p>
</div>
