{{-- Overlay --}}
<div x-show="open" x-transition.opacity class="fixed inset-0 bg-black/40 z-40"></div>

{{-- Modal panel --}}
<div x-show="open" x-transition @keydown.escape.window="close()" @click.self="close()"
    class="fixed inset-0 z-50 grid place-items-center p-4">
    <div class="w-full max-w-3xl bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden" @click.stop>
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <div>
                <div class="text-lg font-semibold text-etagate-blue">
                    Process #<span x-text="current?.id"></span>
                </div>
                <div class="text-xs text-gray-500" x-text="current?.name || ''"></div>
            </div>
            <button type="button" class="w-9 h-9 rounded-full border hover:bg-gray-50" @click="close()">
                <svg class="w-5 h-5 text-gray-700 mx-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.8">
                    <path d="M6 6l12 12M6 18L18 6" />
                </svg>
            </button>
        </div>

        <div class="p-5 space-y-5">
            {{-- Loading / Error --}}
            <template x-if="loading">
                <div class="text-sm text-gray-600">Loading...</div>
            </template>
            <template x-if="error">
                <div class="rounded-xl border border-red-200 bg-red-50 text-red-800 px-4 py-3 text-sm" x-text="error">
                </div>
            </template>

            {{-- Stats rápidas --}}
            <template x-if="detail">
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-gray-50 border rounded-xl p-3">
                        <div class="text-xs text-gray-500">Status</div>
                        <div class="text-sm font-semibold" x-text="detail.status"></div>
                    </div>
                    <div class="bg-gray-50 border rounded-xl p-3">
                        <div class="text-xs text-gray-500">Total images</div>
                        <div class="text-sm font-semibold" x-text="detail.total_images ?? '—'"></div>
                    </div>
                    <div class="bg-gray-50 border rounded-xl p-3">
                        <div class="text-xs text-gray-500">Avg latency (ms)</div>
                        <div class="text-sm font-semibold" x-text="detail.avg_latency_ms ?? '—'"></div>
                    </div>
                    <div class="bg-gray-50 border rounded-xl p-3">
                        <div class="text-xs text-gray-500">Avg confidence</div>
                        <div class="text-sm font-semibold" x-text="detail.avg_confidence ?? '—'"></div>
                    </div>
                </div>
            </template>

            {{-- Modelos del proceso --}}
            <template x-if="detail?.models?.length">
                <div>
                    <div class="text-sm font-semibold text-etagate-blue mb-2">Models</div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="m in detail.models" :key="m.model_uuid">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs
                                         bg-white border border-gray-200">
                                <span class="font-semibold">#<span x-text="m.order_index"></span></span>
                                <span>·</span>
                                <span x-text="m.name || (m.model_uuid ?? '')"></span>
                                <template x-if="m.threshold !== null && m.threshold !== undefined">
                                    <span class="text-gray-500">( <span x-text="Number(m.threshold).toFixed(2)"></span>
                                        )</span>
                                </template>
                                <span class="ml-1 w-1.5 h-1.5 rounded-full"
                                    :class="m.enabled === false ? 'bg-red-500' : 'bg-green-500'"></span>
                            </span>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Último request --}}
            <template x-if="detail?.last_request">
                <div class="space-y-2">
                    <div class="text-sm font-semibold text-etagate-blue">Last request</div>
                    <div class="text-xs text-gray-600">
                        <div>Received: <span x-text="fmt(detail.last_request.received_at)"></span></div>
                        <div>Image: <span class="font-medium" x-text="detail.last_request.image_name"></span></div>
                    </div>
                    <pre class="text-xs bg-gray-50 border rounded-xl p-3 overflow-auto"
                        x-text="JSON.stringify(detail.last_request.results ?? [], null, 2)"></pre>
                </div>
            </template>

            {{-- Insights por modelo --}}
            <template x-if="insights?.models?.length">
                <div>
                    <div class="text-sm font-semibold text-etagate-blue mb-2">Insights</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs border">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="px-3 py-2 text-left">Model</th>
                                    <th class="px-3 py-2 text-right">Inferences</th>
                                    <th class="px-3 py-2 text-right">Pos</th>
                                    <th class="px-3 py-2 text-right">Neg</th>
                                    <th class="px-3 py-2 text-right">Avg conf</th>
                                    <th class="px-3 py-2 text-right">Avg lat</th>
                                    <th class="px-3 py-2 text-right">P95</th>
                                    <th class="px-3 py-2 text-right">Max</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <template x-for="m in insights.models" :key="m.model_uuid">
                                    <tr>
                                        <td class="px-3 py-2" x-text="m.model_name"></td>
                                        <td class="px-3 py-2 text-right" x-text="m.inferences"></td>
                                        <td class="px-3 py-2 text-right" x-text="m.positives"></td>
                                        <td class="px-3 py-2 text-right" x-text="m.negatives"></td>
                                        <td class="px-3 py-2 text-right" x-text="m.avg_confidence ?? '—'"></td>
                                        <td class="px-3 py-2 text-right" x-text="m.avg_latency_ms ?? '—'"></td>
                                        <td class="px-3 py-2 text-right" x-text="m.p95_latency_ms ?? '—'"></td>
                                        <td class="px-3 py-2 text-right" x-text="m.max_latency_ms ?? '—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
