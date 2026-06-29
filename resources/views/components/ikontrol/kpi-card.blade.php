@props([
    'title',
    'value' => '0',
    'meta' => null,
    'tone' => 'gray',
])

@php
    $toneClasses = match ($tone) {
        'emerald' => 'border-emerald-100 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300',
        'sky' => 'border-sky-100 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300',
        'amber' => 'border-amber-100 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300',
        'violet' => 'border-violet-100 bg-violet-50 text-violet-700 dark:border-violet-500/20 dark:bg-violet-500/10 dark:text-violet-300',
        default => 'border-gray-100 bg-gray-50 text-gray-700 dark:border-gray-700/60 dark:bg-gray-700/30 dark:text-gray-300',
    };
@endphp

<div class="rounded-lg border bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700/60 p-5 shadow-xs">
    <div class="flex items-start justify-between gap-4">
        <div>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $title }}</div>
            <div class="mt-2 text-3xl font-bold text-gray-800 dark:text-gray-100">{{ $value }}</div>
            @if($meta)
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $meta }}</div>
            @endif
        </div>
        <div class="h-10 w-10 rounded-lg border flex items-center justify-center {{ $toneClasses }}">
            {{ $slot->isEmpty() ? '' : $slot }}
        </div>
    </div>
</div>
