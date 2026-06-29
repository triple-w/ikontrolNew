@props([
    'title',
    'description' => null,
])

<section {{ $attributes->merge(['class' => 'rounded-lg border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-800 shadow-xs']) }}>
    <header class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
        <h2 class="font-semibold text-gray-800 dark:text-gray-100">{{ $title }}</h2>
        @if($description)
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
        @endif
    </header>
    <div class="p-5">
        {{ $slot }}
    </div>
</section>
