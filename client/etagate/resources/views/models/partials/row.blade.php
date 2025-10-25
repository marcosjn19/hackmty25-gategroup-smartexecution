<tr class="hover:bg-gray-50">
    <td class="px-6 py-4">
        <div class="font-semibold text-etagate-blue">{{ $model->name }}</div>
    </td>
    <td class="px-6 py-4">
        <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ $model->uuid }}</code>
    </td>
    <td class="px-6 py-4 text-gray-600">
        {{ Str::limit($model->description, 80) }}
    </td>
    <td class="px-6 py-4">
        @if($status && $status !== '—')
            <span
                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                {{ $status }}
            </span>
        @else
            <span class="text-gray-400">—</span>
        @endif
    </td>
    <td class="px-6 py-4 text-right">
        <div class="flex items-center justify-end gap-2">
            {{-- (Optional) edit link reserved for later --}}
            {{-- <a href="{{ route('models.edit', $model) }}"
                class="text-sm text-etagate-blue hover:text-etagate-orange font-semibold">Edit</a> --}}
            <button @click="confirm('{{ route('models.destroy', $model) }}', '{{ e($model->name) }}')"
                class="text-sm text-red-600 hover:text-red-700 font-semibold">
                Delete
            </button>
        </div>
    </td>
</tr>
