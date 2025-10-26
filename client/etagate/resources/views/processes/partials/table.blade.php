<table class="min-w-full bg-white border border-gray-100 shadow-sm rounded-2xl overflow-hidden">
    <thead class="bg-gray-50">
        <tr class="text-left text-sm text-gray-600">
            <th class="px-4 py-3 w-20">ID</th>
            <th class="px-4 py-3">Name</th>
            <th class="px-4 py-3">Models</th>
            <th class="px-4 py-3 w-16 text-center">View</th>
        </tr>
    </thead>

    <tbody class="divide-y divide-gray-100">
        @forelse ($processes as $p)
            <tr class="text-sm">
                {{-- ID --}}
                <td class="px-4 py-3 font-semibold text-etagate-blue">#{{ $p->id }}</td>

                {{-- Name --}}
                <td class="px-4 py-3">
                    <div class="max-w-xs truncate" title="{{ $p->name }}">
                        {{ $p->name }}
                    </div>
                </td>

                {{-- Models --}}
                <td class="px-4 py-3">
                    @include('processes.partials.models-badges', ['process' => $p])
                </td>

                {{-- View --}}
                <td class="px-4 py-3 text-center">
                    <button type="button"
                        class="inline-flex items-center justify-center w-9 h-9 rounded-full border border-gray-200 hover:bg-gray-50"
                        @click.prevent="openFor({ id: {{ $p->id }}, name: @js($p->name) })"
                        title="View details">
                        <svg class="w-5 h-5 text-gray-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-4 py-6 text-center text-gray-500">No processes found.</td>
            </tr>
        @endforelse
    </tbody>
</table>
