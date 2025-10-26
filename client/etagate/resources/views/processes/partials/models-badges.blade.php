@php
    // Asegura arreglo y orden por order_index
    $mods = collect($process->models ?? [])
        ->map(fn($m) => is_array($m) ? $m : (array) $m)
        ->sortBy('order_index')
        ->values();
@endphp

<div class="flex flex-wrap items-center gap-2">
    @forelse ($mods as $m)
        @php
            $name = $m['name'] ?? $m['model_uuid'] ?? 'Unknown';
            $order = $m['order_index'] ?? null;
            $off = array_key_exists('enabled', $m) && $m['enabled'] === false;
        @endphp

        <span
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs ring-1 {{ $off ? 'bg-gray-100 text-gray-600 ring-gray-200' : 'bg-orange-50 text-orange-700 ring-orange-200' }}"
            title="Order {{ $order ?? '—' }}{{ $off ? ' • disabled' : '' }}">
            <span class="font-medium">{{ $name }}</span>
            @if($order)<span class="opacity-70">#{{ $order }}</span>@endif>
            @if($off)
                <svg class="w-3.5 h-3.5 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 18L18 6M6 6l12 12" />
                </svg>
            @endif
        </span>
    @empty
        <span class="text-xs text-gray-500">No models selected.</span>
    @endforelse
</div>
