@props([
    'columns' => [],
    'message' => 'Sin registros para mostrar.',
])

<div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700/60 bg-white dark:bg-gray-800">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/30 text-gray-500 dark:text-gray-400">
                <tr>
                    @foreach($columns as $column)
                        <th class="px-4 py-3 text-left font-medium">{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="px-4 py-8 text-center text-gray-500 dark:text-gray-400" colspan="{{ max(count($columns), 1) }}">
                        {{ $message }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
