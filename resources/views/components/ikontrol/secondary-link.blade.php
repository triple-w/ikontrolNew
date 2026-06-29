@props(['href'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600 whitespace-nowrap']) }}>
    {{ $slot }}
</a>
