@extends('layouts.app')

@php($defaultThreshold = 0.85)

@section('content')
    <div class="max-w-5xl mx-auto" x-data="procCreate(@js(
        $models->map(fn($m) => ['uuid' => $m->uuid, 'name' => $m->name])->values()
    ), @js($defaultThreshold))">

        {{-- Hero --}}
        <div class="bg-gradient-to-br from-etagate-blue to-[#1a2835] rounded-3xl p-8 md:p-10 text-white shadow-2xl mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-white/15 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-width="1.8" d="M3 7a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
                            <path stroke-width="1.8" d="M3 9l7.7 4.7a3 3 0 003 0L21 9" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-3xl md:text-4xl font-bold">Create process</h2>
                        <p class="text-white/80 mt-2">Pick a name, select one or more models, and set their order.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Errors --}}
        @if ($errors->any())
            <div class="mb-6 rounded-xl bg-red-50 text-red-800 px-4 py-3 border border-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('processes.store') }}"
            class="bg-white rounded-3xl shadow-xl border border-gray-100 p-6 md:p-8">
            @csrf

            {{-- Name --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold text-etagate-blue mb-2">Name</label>
                <input type="text" name="name" x-model="name" required maxlength="255"
                    class="w-full rounded-xl border-gray-300 focus:border-etagate-orange focus:ring-etagate-orange"
                    placeholder="e.g., Ramp Safety – Gate 12">
            </div>

            {{-- Picker (search + check list) --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold text-etagate-blue mb-2">Models</label>
                <div class="grid md:grid-cols-2 gap-4">
                    {{-- Available list --}}
                    <div class="border rounded-2xl p-3">
                        <div class="flex items-center gap-2 mb-2">
                            <input x-model="query" type="search" inputmode="search" placeholder="Search models…"
                                class="w-full rounded-xl border-gray-300 focus:border-etagate-orange focus:ring-etagate-orange">
                            <button type="button" class="px-3 py-2 rounded-xl border hover:bg-gray-50"
                                @click="selectAllFiltered()">Select all</button>
                        </div>
                        <div class="max-h-64 overflow-auto divide-y">
                            <template x-for="m in filtered" :key="m.uuid">
                                <label class="flex items-center justify-between py-2 px-2 gap-3 cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" class="rounded" :checked="selected.has(m.uuid)"
                                            @change="toggle(m)">
                                        <span class="text-sm" x-text="m.name"></span>
                                    </div>
                                    <button type="button" class="text-etagate-orange text-sm hover:underline"
                                        @click="addOne(m)" x-show="!selected.has(m.uuid)">Add</button>
                                    <span class="text-xs text-gray-400" x-show="selected.has(m.uuid)">Selected</span>
                                </label>
                            </template>
                            <p class="text-xs text-gray-500 p-2" x-show="!filtered.length">No matches.</p>
                        </div>
                    </div>

                    {{-- Selected with ordering --}}
                    <div class="border rounded-2xl">
                        <div class="flex items-center justify-between p-3">
                            <p class="text-sm font-semibold text-etagate-blue">Selected (<span x-text="rows.length"></span>)
                            </p>
                            <button type="button" class="text-sm text-red-600 hover:underline" @click="clearAll()"
                                x-show="rows.length">Clear all</button>
                        </div>

                        <div class="max-h-64 overflow-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-gray-600">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Model</th>
                                        <th class="px-3 py-2 w-28 text-left">Order</th>
                                        <th class="px-3 py-2 w-40 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(r, idx) in rows" :key="r.uuid">
                                        <tr class="border-t" draggable="true" @dragstart="dragStart(idx)" @dragover.prevent
                                            @drop="dragDrop(idx)" @keydown.arrow-up.prevent="move(idx,-1)"
                                            @keydown.arrow-down.prevent="move(idx,1)" tabindex="0">
                                            <td class="px-3 py-2 align-middle">
                                                <span class="font-medium" x-text="r.name"></span>

                                                {{-- Hidden payload for backend --}}
                                                <input type="hidden" :name="`models[${idx}][model_uuid]`" :value="r.uuid">
                                                <input type="hidden" :name="`models[${idx}][name]`" :value="r.name">
                                                <input type="hidden" :name="`models[${idx}][order_index]`" :value="r.order">
                                                <input type="hidden" :name="`models[${idx}][threshold]`"
                                                    value="{{ $defaultThreshold }}">
                                            </td>
                                            <td class="px-3 py-2 align-middle">
                                                <input type="number" min="1" :max="rows.length"
                                                    class="w-20 rounded-lg border px-2 py-1" x-model.number.lazy="r.order"
                                                    @change="onOrderInput(idx)">
                                            </td>
                                            <td class="px-3 py-2 align-middle text-right">
                                                <div class="inline-flex items-center gap-2">
                                                    <button type="button"
                                                        class="px-2 py-1 rounded-lg border hover:bg-gray-50"
                                                        :disabled="idx===0" @click="move(idx,-1)"
                                                        aria-label="Move up">↑</button>
                                                    <button type="button"
                                                        class="px-2 py-1 rounded-lg border hover:bg-gray-50"
                                                        :disabled="idx===rows.length-1" @click="move(idx,1)"
                                                        aria-label="Move down">↓</button>
                                                    <button type="button" class="px-2 py-1 text-red-600 hover:underline"
                                                        @click="remove(idx)" aria-label="Remove">Remove</button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>

                            <p class="text-xs text-gray-500 p-3" x-show="!rows.length">No models selected yet.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between flex-wrap gap-3">
                <a href="{{ route('processes.index') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full text-white font-semibold bg-gradient-to-r from-etagate-orange to-orange-600 hover:shadow-lg">
                    Save
                </button>
            </div>
        </form>
    </div>

    <script>
        /**
         * Alpine component for Processes Create
         * @param {Array<{uuid:string,name:string}>} allModels
         * @param {number} thresholdConst
         */
        function procCreate(allModels, thresholdConst) {
            return {
                name: '',
                query: '',
                all: allModels,
                selected: new Set(),   // uuids
                rows: [],              // [{uuid,name,order}]
                dragging: null,

                get filtered() {
                    const q = this.query.trim().toLowerCase();
                    return this.all.filter(m =>
                        !this.selected.has(m.uuid) &&
                        (q === '' || m.name.toLowerCase().includes(q))
                    );
                },

                // ----- selection -----
                toggle(m) {
                    if (this.selected.has(m.uuid)) { this.removeByUuid(m.uuid); }
                    else { this.addOne(m); }
                },
                addOne(m) {
                    if (this.selected.has(m.uuid)) return;
                    this.selected.add(m.uuid);
                    this.rows.push({ uuid: m.uuid, name: m.name, order: this.rows.length + 1 });
                },
                remove(i) {
                    const u = this.rows[i].uuid;
                    this.selected.delete(u);
                    this.rows.splice(i, 1);
                    this.reindex();
                },
                removeByUuid(u) {
                    const i = this.rows.findIndex(r => r.uuid === u);
                    if (i >= 0) this.remove(i);
                },
                clearAll() {
                    this.selected.clear();
                    this.rows = [];
                },
                selectAllFiltered() {
                    this.filtered.forEach(m => this.addOne(m));
                },

                // ----- ordering -----
                reindex() { this.rows.forEach((r, i) => r.order = i + 1); },
                move(i, delta) {
                    const j = i + delta;
                    if (j < 0 || j >= this.rows.length) return;
                    const [item] = this.rows.splice(i, 1);
                    this.rows.splice(j, 0, item);
                    this.reindex();
                },
                onOrderInput(i) {
                    let val = parseInt(this.rows[i].order, 10);
                    if (!Number.isFinite(val)) val = i + 1;
                    val = Math.max(1, Math.min(val, this.rows.length));
                    if (val - 1 === i) { this.reindex(); return; }
                    const [it] = this.rows.splice(i, 1);
                    this.rows.splice(val - 1, 0, it);
                    this.reindex();
                },
                // drag & drop
                dragStart(i) { this.dragging = i; },
                dragDrop(idx) {
                    const from = this.dragging;
                    this.dragging = null;
                    if (from === null || from === idx) return;
                    const [it] = this.rows.splice(from, 1);
                    this.rows.splice(idx, 0, it);
                    this.reindex();
                },
            }
        }
    </script>
@endsection
