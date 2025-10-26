@extends('layouts.app')

@section('page_title', 'Run Process')
@section('html_title', 'Etagate - Run Process')

@section('content')
<div x-data="processRunner(@js($process))" x-init="init()" class="space-y-6">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3 md:gap-0">
        <div class="w-full md:w-auto">
            <h2 class="text-2xl font-bold text-etagate-blue">Run process: <span class="font-normal">{{ $process->name }}</span></h2>
            <p class="text-sm text-gray-600">Validate images model-by-model. Upload one image at a time, validate, then continue.</p>
        </div>
        <div class="w-full md:w-auto flex items-center justify-start md:justify-end space-x-2">
            <button x-show="!finished" @click="finalizeCurrent()" class="flex-shrink-0 px-4 py-2 bg-etagate-orange text-white rounded-full font-semibold hover:shadow">Finalize model</button>
            <a href="{{ route('processes.index') }}" class="flex-shrink-0 px-4 py-2 border rounded-full text-gray-700">Back to list</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-4">
            <div class="bg-white p-4 rounded-lg shadow-sm border">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-semibold">Current model</h3>
                        <p class="text-sm text-gray-600" x-text="currentModel.name"></p>
                    </div>
                    <div class="text-right text-sm text-gray-500">
                        <div>Model <span x-text="currentModelIndex + 1"></span> / <span x-text="totalModels"></span></div>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">Capture or upload image (one at a time)</label>

                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button type="button" @click.prevent="startCamera()" x-show="!cameraActive" class="px-3 py-2 bg-etagate-blue text-white rounded-full text-sm">Use camera</button>
                        <button type="button" @click.prevent="stopCamera()" x-show="cameraActive" class="px-3 py-2 border rounded-full text-sm">Close camera</button>

                        <!-- file upload removed: capture-only workflow -->

                        <div class="ml-auto flex items-center space-x-2">
                            <button :disabled="!hasFile || validating" @click.prevent="validateImage()" class="px-4 py-2 bg-etagate-blue text-white rounded-full font-semibold disabled:opacity-50">Validate</button>
                            <button @click.prevent="clearFile()" class="px-3 py-2 border rounded">Clear</button>
                        </div>
                    </div>

                    <template x-if="cameraActive">
                        <div class="mt-3">
                            <video x-ref="video" autoplay playsinline class="w-full max-h-80 bg-black rounded border object-cover"></video>
                            <div class="mt-2 flex items-center space-x-2">
                                <button @click.prevent="captureFromCamera()" class="px-4 py-2 bg-etagate-orange text-white rounded-full font-semibold">Capture</button>
                                <button @click.prevent="stopCamera()" class="px-3 py-2 border rounded">Close</button>
                            </div>
                        </div>
                    </template>
                </div>

                <template x-if="previewSrc">
                    <div class="mt-4">
                        <p class="text-sm text-gray-600">Preview</p>
                        <img :src="previewSrc" class="mt-2 max-h-80 rounded border" alt="preview" />
                    </div>
                </template>

                <div class="mt-4">
                    <h4 class="text-sm font-semibold">Last result</h4>
                    <div class="mt-2 p-3 bg-gray-50 rounded">
                        <template x-if="lastResult">
                            <div>
                                <div>Approved: <span x-text="lastResult.approved ? 'Yes' : (lastResult.approved === false ? 'No' : 'N/A')"></span></div>
                                <div>Confidence: <span x-text="lastResult.confidence ?? 'N/A'"></span></div>
                                <div>Request id: <span x-text="lastResult.request_id ?? '-' "></span></div>
                            </div>
                        </template>
                        <template x-if="!lastResult">
                            <div class="text-sm text-gray-500">No validation yet.</div>
                        </template>
                    </div>
                </div>

            </div>

            <div class="mt-4 bg-white p-4 rounded-lg border shadow-sm">
                <h4 class="font-semibold">Model list</h4>
                <ul class="mt-3 space-y-2">
                    <template x-for="(m, idx) in models" :key="idx">
                        <li class="flex items-center justify-between p-2 rounded border" :class="{'bg-green-50': idx === currentModelIndex}">
                            <div>
                                <div class="font-medium" x-text="m.name || m.model_uuid"></div>
                                <div class="text-sm text-gray-500">UUID: <span x-text="m.model_uuid"></span></div>
                            </div>
                            <div class="text-sm text-gray-600">Index: <span x-text="m.order_index ?? (idx+1)"></span></div>
                        </li>
                    </template>
                </ul>
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white p-4 rounded-lg border shadow-sm">
                <h4 class="font-semibold">Process info</h4>
                <div class="mt-2 text-sm text-gray-600">
                    <div>ID: {{ $process->id }}</div>
                    <div>Name: {{ $process->name }}</div>
                    <div>Default threshold: {{ $process->default_threshold ?? '—' }}</div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg border shadow-sm">
                <h4 class="font-semibold">Controls</h4>
                <div class="mt-3 space-y-2 text-sm">
                    <div>Current model: <span class="font-medium" x-text="currentModel.name"></span></div>
                    <div>Has file: <span x-text="hasFile ? 'Yes' : 'No'"></span></div>
                    <div>Validating: <span x-text="validating ? 'Yes' : 'No'"></span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function processRunner(process) {
    return {
        process: process,
        models: process.models || [],
        totalModels: (process.models || []).length,
        currentModelIndex: 0,
        get currentModel() { return this.models[this.currentModelIndex] || {}; },
    hasFile: false,
    previewSrc: null,
    previewUrl: null, // object URL for captured blob
    imageBlob: null, // when capturing from camera
    mediaStream: null,
    cameraActive: false,
    validating: false,
    lastResult: null,
    finished: false,

        init() {
            // ensure at least one model
            if (!this.totalModels) {
                alert('This process has no models to run.');
            }
        },

        clearFile() {
            this.imageBlob = null;
            this.hasFile = false;
            this.previewSrc = null;
            if (this.previewUrl) {
                try { URL.revokeObjectURL(this.previewUrl); } catch(e){}
                this.previewUrl = null;
            }
        },

        async validateImage() {
            if (!this.hasFile || !this.imageBlob) return alert('No image to validate — please capture one first.');
            this.validating = true;

            const form = new FormData();
            const payload = this.imageBlob;
            const filename = 'capture.png';
            form.append('image', payload, filename);
            // send model_uuid to select the specific model for validation
            form.append('model_uuid', this.currentModel.model_uuid);

            try {
                const res = await fetch(`/api/procesos/${this.process.id}/validate`, {
                    method: 'POST',
                    body: form,
                });
                const json = await res.json();
                if (!res.ok) {
                    alert('Validation failed: ' + (json.message || json.error || res.statusText));
                } else {
                    this.lastResult = json.last_request?.results?.[0] ?? json; // adapt to controller output
                    // after validating, clear file and stay on current model for next image
                    this.clearFile();
                }
            } catch (err) {
                console.error(err);
                alert('Network error during validation');
            } finally {
                this.validating = false;
            }
        },

        // Camera helpers
        async startCamera() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Camera not supported in this browser.');
                return;
            }
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
                this.mediaStream = stream;
                // show video element first, then attach stream to avoid $refs.video being undefined
                this.cameraActive = true;
                // clear previous captures
                this.hasFile = false;
                this.imageBlob = null;
                this.previewSrc = null;
                // wait for DOM update so $refs.video exists
                if (typeof this.$nextTick === 'function') {
                    this.$nextTick(() => { if (this.$refs.video) this.$refs.video.srcObject = stream; });
                } else {
                    setTimeout(() => { if (this.$refs.video) this.$refs.video.srcObject = stream; }, 50);
                }
            } catch (err) {
                console.error(err);
                alert('Unable to access camera: ' + (err.message || err));
            }
        },

        stopCamera() {
            if (this.mediaStream) {
                this.mediaStream.getTracks().forEach(t => t.stop());
                this.mediaStream = null;
            }
            if (this.$refs.video) this.$refs.video.srcObject = null;
            this.cameraActive = false;
        },

        captureFromCamera() {
            const video = this.$refs.video;
            if (!video || !video.videoWidth) {
                alert('Camera not ready yet.');
                return;
            }
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            canvas.toBlob((blob) => {
                if (!blob) {
                    alert('Capture failed');
                    return;
                }
                this.imageBlob = blob;
                // preview via object URL (more efficient than dataURL for large images)
                if (this.previewUrl) try { URL.revokeObjectURL(this.previewUrl); } catch(e){}
                this.previewUrl = URL.createObjectURL(blob);
                this.previewSrc = this.previewUrl;
                this.hasFile = true;
                // stop camera after capture to save battery on mobile
                this.stopCamera();
            }, 'image/png');
        },

        finalizeCurrent() {
            // If last model, finalize process
            if (this.currentModelIndex >= this.totalModels - 1) {
                if (!confirm('Finalize process?')) return;
                this.finishProcess();
                return;
            }

            // otherwise advance to next model
            if (!confirm('Finalize current model and continue to next?')) return;
            this.currentModelIndex++;
            this.clearFile();
            this.lastResult = null;
        },

        finishProcess() {
            // Mark as finished in UI; you can call backend to update status if needed
            this.finished = true;
            alert('Process finalized locally.');
            // Optionally redirect back to list
            window.location.href = '{{ route('processes.index') }}';
        }
    }
}
</script>
@endsection
