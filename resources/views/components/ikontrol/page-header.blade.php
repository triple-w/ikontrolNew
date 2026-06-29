@props([
    'title',
    'description' => '',
    'breadcrumbs' => [],
    'actionHref' => null,
    'actionLabel' => null,
])

<div class="mb-8">
    @if(!empty($breadcrumbs))
        <nav class="mb-3 text-xs font-medium text-gray-500 dark:text-gray-400" aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center gap-2">
                @foreach($breadcrumbs as $breadcrumb)
                    <li class="flex items-center gap-2">
                        @if(!$loop->first)
                            <span class="text-gray-300 dark:text-gray-600">/</span>
                        @endif
                        <span>{{ $breadcrumb }}</span>
                    </li>
                @endforeach
            </ol>
        </nav>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 dark:text-gray-100">{{ $title }}</h1>
            @if($description !== '')
                <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
            @endif
        </div>

        @if($actionHref && $actionLabel)
            <x-ikontrol.primary-link href="{{ $actionHref }}">{{ $actionLabel }}</x-ikontrol.primary-link>
        @endif
    </div>
</div>
