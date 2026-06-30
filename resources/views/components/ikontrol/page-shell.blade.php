@props([
    'maxWidth' => '7xl',
])

@php
    $maxWidthClass = match ($maxWidth) {
        '3xl' => 'max-w-3xl',
        '5xl' => 'max-w-5xl',
        'wide', '9xl' => 'max-w-9xl',
        default => 'max-w-7xl',
    };
@endphp

<div {{ $attributes->merge(['class' => "ik-page-shell mx-auto w-full min-w-0 {$maxWidthClass} px-4 py-6 sm:px-6 lg:px-8 lg:py-8"]) }}>
    <div class="space-y-6">
        {{ $slot }}
    </div>
</div>
