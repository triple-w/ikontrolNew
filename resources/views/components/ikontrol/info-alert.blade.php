@props(['title' => 'Informacion'])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-200']) }}>
    <div class="text-sm font-semibold">{{ $title }}</div>
    <div class="mt-1 text-sm">{{ $slot }}</div>
</div>
