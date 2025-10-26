@php
    // Optimistic ETA between 1 and 5 minutes
    $eta = rand(1, 5) . ' min';

    // Efficiency between 85.2 and 97.1 (one decimal)
    $efficiency = number_format(mt_rand(852, 971) / 10, 1);

    // Availability (bool) provided by parent view
    $isAvailable = (bool) ($available ?? false);
@endphp

<div x-data="{
        open: false,
        positive: true,
        isDragging: false,
        files: [],
        setFiles(list) {
            this.files = [...list];
            // Mirror to hidden file input so the form submits
            try {
                const dt = new DataTransfer();
                this.files.forEach(f => dt.items.add(f));
                $refs.fileInput.files = dt.files;
            } catch (e) {}
        },
        onDrop(e) {
            e.preventDefault();
            this.isDragging = false;
            if (e.dataTransfer?.files?.length) this.setFiles(e.dataTransfer.files);
        },
        onDragOver(e) { e.preventDefault(); this.isDragging = true; },
        onDragLeave() { this.isDragging = false; }
    }" x-cloak class="bg-white rounded-2xl shadow-lg border border-gray-100 transition-all overflow-visible pb-4">

    {{-- Top: image/placeholder --}}
    <div class="h-36 md:h-40 relative overflow-hidden rounded-t-2xl">
        <div class="absolute inset-0 bg-gradient-to-br from-etagate-blue to-[#1a2835]"></div>
        <div class="absolute inset-0 flex items-center justify-center">
            <svg class="w-12 h-12 opacity-80 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.5" aria-hidden="true">
                <path d="M3 7a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
                <path d="M3 9l7.5 4.5a3 3 0 003 0L21 9" />
            </svg>
        </div>
    </div>

    {{-- Content --}}
    <div class="p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-etagate-blue truncate" title="{{ $model->name }}">
                    {{ $model->name }}
                </h3>
                <p class="mt-1 text-xs text-gray-500 line-clamp-2">
                    {{ $model->description }}
                </p>
            </div>

            {{-- Availability --}}
            <div class="shrink-0 inline-flex items-center gap-2 text-sm">
                <span
                    class="w-2.5 h-2.5 rounded-full {{ $isAvailable ? 'bg-green-500' : 'bg-gray-300' }} {{ $isAvailable ? 'animate-pulse' : '' }}"></span>
                <span class="{{ $isAvailable ? 'text-green-700' : 'text-gray-500' }}">
                    {{ $isAvailable ? 'Available' : 'Unavailable' }}
                </span>
            </div>
        </div>

        {{-- ETA & Efficiency --}}
        <div class="mt-3 flex items-center gap-4 text-sm text-gray-600">
            <div class="flex items-center gap-1" title="ETA">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    aria-hidden="true">
                    <path d="M12 8v4l3 3" />
                    <circle cx="12" cy="12" r="9" />
                </svg>
                <span>{{ $eta }}</span>
            </div>
            <div class="flex items-center gap-1" title="Efficiency">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    aria-hidden="true">
                    <path d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span>{{ $efficiency }}%</span>
            </div>
        </div>

        {{-- Actions (no Close) --}}
        <div class="mt-4 flex items-center justify-between gap-2 flex-wrap">
            <div class="flex items-center gap-2">
                <button type="button"
                    class="text-xs px-3 py-1.5 rounded-full border border-gray-200 text-gray-700 hover:bg-gray-50"
                    x-on:click="open = !open">
                    <span x-show="!open">Add samples</span>
                    <span x-show="open">Hide samples</span>
                </button>

                {{-- NUEVO: Train --}}
                <form method="POST" action="{{ route('models.train', $model) }}">
                    @csrf
                    <button type="submit" class="text-xs px-3 py-1.5 rounded-full text-white font-semibold bg-gradient-to-r from-etagate-orange to-orange-600 hover:shadow-lg">
                        Train
                    </button>
                </form>
            </div>

            <div class="flex items-center gap-2">
                <button type="button"
                    class="text-xs px-3 py-1.5 rounded-full border border-gray-200 text-gray-700 hover:bg-gray-50"
                    x-on:click="confirm('#delete-{{ $model->id }}', '{{ $model->name }}')">
                    Delete
                </button>
                <form id="delete-{{ $model->id }}" class="hidden" method="POST"
                    action="{{ route('models.destroy', $model) }}">
                    @csrf @method('DELETE')
                </form>
            </div>
        </div>
    </div>

    {{-- Upload panel (expands card) --}}
    <div x-show="open" x-collapse class="px-4">
        <form class="mt-3 border rounded-xl p-4 bg-gray-50/60" action="{{ route('models.samples.store', $model) }}"
            method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="type" :value="positive ? 'positive' : 'negative'">

            <div class="flex items-center justify-between mb-4">
                <div class="text-sm font-semibold text-etagate-blue">Sample type</div>

                {{-- Switch: Negative (left/red) | Positive (right/green) --}}
                <div class="flex items-center gap-3 select-none">
                    <span class="text-sm"
                        :class="positive ? 'text-gray-400' : 'text-red-600 font-semibold'">Negative</span>
                    <button type="button" role="switch" :aria-checked="positive.toString()"
                        @click="positive = !positive"
                        class="relative w-20 h-8 rounded-full transition-colors duration-200 outline-none focus:ring-2 focus:ring-orange-400"
                        :class="positive ? 'bg-green-500/90' : 'bg-red-500/90'">
                        <span class="absolute inset-0 flex items-center justify-between px-2 text-[11px] text-white">
                            <span>−</span><span>+</span>
                        </span>
                        <span
                            class="absolute top-1 left-1 w-6 h-6 bg-white rounded-full shadow transition-transform duration-200"
                            :class="positive ? 'translate-x-12' : 'translate-x-0'"></span>
                    </button>
                    <span class="text-sm"
                        :class="positive ? 'text-green-600 font-semibold' : 'text-gray-400'">Positive</span>
                </div>
            </div>

            {{-- Drag & drop area + click to open file picker --}}
            <div class="grid gap-3">
                <label class="text-sm font-semibold text-etagate-blue">Images</label>

                <div class="rounded-xl border-2 border-dashed p-6 bg-white text-center transition cursor-pointer"
                    :class="isDragging ? 'border-etagate-orange bg-orange-50/50' : 'border-gray-300 hover:border-etagate-orange'"
                    @dragover="onDragOver" @dragleave="onDragLeave" @drop="onDrop" @click="$refs.fileInput.click()">

                    <input x-ref="fileInput" type="file" name="images[]" accept="image/*" multiple class="hidden"
                        @change="setFiles($event.target.files)">

                    <div class="flex flex-col items-center gap-2">
                        <svg class="w-8 h-8 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-width="1.5" d="M12 16v-8m-4 4h8" />
                            <rect x="3" y="3" width="18" height="18" rx="4" ry="4" stroke-width="1.5" />
                        </svg>
                        <p class="text-sm text-gray-600">
                            <span class="font-semibold text-etagate-orange">Drag & drop</span> your images here
                            or <span class="font-semibold text-etagate-orange">choose files</span>.
                        </p>
                        <p class="text-xs text-gray-500">You can choose one or multiple images.</p>

                        <template x-if="files.length">
                            <p class="text-xs text-gray-600 mt-1" x-text="files.length + ' file(s) selected'"></p>
                        </template>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 mt-2">
                    <button type="button"
                        class="px-4 py-2 rounded-full border border-gray-200 text-gray-700 hover:bg-gray-50"
                        @click="files=[]; $refs.fileInput.value=null; open=false">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-5 py-2 rounded-full text-white font-semibold bg-gradient-to-r from-etagate-orange to-orange-600 hover:shadow-lg">
                        Upload
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
