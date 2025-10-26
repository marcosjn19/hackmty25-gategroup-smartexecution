@php
    $eta = rand(1, 5) . ' min';
    $efficiency = number_format(mt_rand(852, 971) / 10, 1);
    $isAvailable = (bool) ($available ?? false);
    $cardId = $model->uuid ?: 'm-' . $model->id;
@endphp

<div x-data="modelCard({
        id: @js($cardId),
        uploadUrl: '{{ route('models.samples.store', $model) }}'
    })" x-init="init()" x-cloak
    class="relative bg-white rounded-2xl shadow-lg border border-gray-100 transition-all overflow-visible">

    {{-- Header --}}
    <div class="h-36 md:h-40 relative overflow-hidden rounded-t-2xl">
        <div class="absolute inset-0 bg-gradient-to-br from-etagate-blue to-[#1a2835]"></div>
        <div class="absolute inset-0 flex items-center justify-center">
            <svg class="w-12 h-12 opacity-80 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M3 7a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
                <path d="M3 9l7.5 4.5a3 3 0 003 0L21 9" />
            </svg>
        </div>
    </div>

    {{-- Content --}}
    <div class="p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-etagate-blue truncate" title="{{ $model->name }}">{{ $model->name }}</h3>
                <p class="mt-1 text-xs text-gray-500 line-clamp-2">{{ $model->description }}</p>
            </div>

            <div class="shrink-0 inline-flex items-center gap-2 text-sm">
                <span class="w-2.5 h-2.5 rounded-full {{ $isAvailable ? 'bg-green-500' : 'bg-gray-300' }} {{ $isAvailable ? 'animate-pulse' : '' }}"></span>
                <span class="{{ $isAvailable ? 'text-green-700' : 'text-gray-500' }}">{{ $isAvailable ? 'Available' : 'Unavailable' }}</span>
            </div>
        </div>

        {{-- KPIs --}}
        <div class="mt-3 flex items-center gap-4 text-sm text-gray-600">
            <div class="flex items-center gap-1" title="ETA">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M12 8v4l3 3" /><circle cx="12" cy="12" r="9" />
                </svg>
                <span>{{ $eta }}</span>
            </div>
            <div class="flex items-center gap-1" title="Efficiency">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span>{{ $efficiency }}%</span>
            </div>
        </div>

        {{-- Actions --}}
        <div class="mt-4 flex items-center justify-between gap-2 flex-wrap pb-4">
            <div class="flex items-center gap-2">
                <button type="button"
                        class="text-xs px-3 py-1.5 rounded-full border border-gray-200 text-gray-700 hover:bg-gray-50"
                        @click.prevent="openToggle()" @keydown.escape.window="open=false">
                    <span x-show="!open">Add samples</span>
                    <span x-show="open">Hide samples</span>
                </button>

                <form method="POST" action="{{ route('models.train', $model) }}">
                    @csrf
                    <button type="submit"
                            class="text-xs px-3 py-1.5 rounded-full text-white font-semibold bg-gradient-to-r from-etagate-orange to-orange-600 hover:shadow-lg">
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
                <form id="delete-{{ $model->id }}" class="hidden" method="POST" action="{{ route('models.destroy', $model) }}">
                    @csrf @method('DELETE')
                </form>
            </div>
        </div>
    </div>

    {{-- Upload popover (NO form) --}}
    <div x-show="open" x-transition.opacity.scale.origin.top @click.outside="open=false"
         class="absolute left-0 right-0 top-full mt-3 z-50">
        <div class="border rounded-2xl p-4 bg-white shadow-2xl ring-1 ring-black/5">
            {{-- Sample type --}}
            <div class="flex items-center justify-between mb-4">
                <div class="text-sm font-semibold text-etagate-blue">Sample type</div>
                <div class="flex items-center gap-3 select-none">
                    <span class="text-sm" :class="positive ? 'text-gray-400' : 'text-red-600 font-semibold'">Negative</span>
                    <button type="button" role="switch" :aria-checked="positive.toString()"
                            @click="positive = !positive"
                            class="relative w-20 h-8 rounded-full transition-colors duration-200 outline-none focus:ring-2 focus:ring-orange-400"
                            :class="positive ? 'bg-green-500/90' : 'bg-red-500/90'">
                        <span class="absolute inset-0 flex items-center justify-between px-2 text-[11px] text-white">
                            <span>−</span><span>+</span>
                        </span>
                        <span class="absolute top-1 left-1 w-6 h-6 bg-white rounded-full shadow transition-transform duration-200"
                              :class="positive ? 'translate-x-12' : 'translate-x-0'"></span>
                    </button>
                    <span class="text-sm" :class="positive ? 'text-green-600 font-semibold' : 'text-gray-400'">Positive</span>
                </div>
            </div>

            {{-- Dropzone --}}
            <div class="grid gap-3">
                <label class="text-sm font-semibold text-etagate-blue">Images</label>

                <div class="rounded-xl border-2 border-dashed p-6 bg-white text-center transition cursor-pointer"
                     :class="isDragging ? 'border-etagate-orange bg-orange-50/50' : 'border-gray-300 hover:border-etagate-orange'"
                     @dragover.prevent="isDragging=true"
                     @dragleave="isDragging=false"
                     @drop.prevent="isDragging=false; add($event.dataTransfer.files)"
                     @click="$refs.fileInput.click()">

                    <input x-ref="fileInput" type="file" accept="image/*" multiple class="hidden"
                           @change="add($event.target.files)">

                    <div class="flex flex-col items-center gap-2">
                        <svg class="w-8 h-8 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-width="1.5" d="M12 16v-8m-4 4h8" />
                            <rect x="3" y="3" width="18" height="18" rx="4" ry="4" stroke-width="1.5" />
                        </svg>
                        <p class="text-sm text-gray-600">
                            <span class="font-semibold text-etagate-orange">Drag & drop</span> your images here
                            or <span class="font-semibold text-etagate-orange">choose files</span>.
                        </p>
                        <p class="text-xs text-gray-500">Max 10 MB per image. Uploads go one by one.</p>
                    </div>
                </div>

                {{-- Summary + progress --}}
                <div class="mt-2 flex items-center justify-between" x-show="queue.length">
                    <div class="text-sm text-gray-700">
                        <span class="font-medium" x-text="queue.length"></span> selected ·
                        <span x-text="bytesHuman(summaryBytes())"></span>
                    </div>
                    <div class="w-40 h-2 bg-gray-200 rounded-full overflow-hidden" x-show="overall() > 0">
                        <div class="h-2 bg-etagate-orange rounded-full" :style="'width:' + overall() + '%'"></div>
                    </div>
                </div>

                <template x-if="queue.some(i => i.status==='error')">
                    <div class="text-xs text-red-600">Some files were skipped or failed. Hover the button to see details.</div>
                </template>

                <div class="flex items-center justify-end gap-2 mt-2">
                    <button type="button"
                            class="px-4 py-2 rounded-full border border-gray-200 text-gray-700 hover:bg-gray-50"
                            @click="clear(); open=false">
                        Cancel
                    </button>
                    <button type="button"
                            class="px-5 py-2 rounded-full text-white font-semibold bg-gradient-to-r from-etagate-orange to-orange-600 hover:shadow-lg"
                            @click="uploadAll($event)">
                        Upload
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
