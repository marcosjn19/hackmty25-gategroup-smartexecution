<?php

namespace App\Http\Controllers;

use App\Models\Process;
use App\Models\Modell;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use App\Services\ExternalModelApi;

class ProcessController extends Controller
{
    /** LIST: GET /api/procesos?status=&q= */
    // ====== LIST (WEB o API) ======
    public function index(Request $request)
    {
        $q = Process::query()->latest();

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($search = $request->query('q')) {
            $q->where('name', 'like', "%{$search}%");
        }

        $perPage = (int) $request->query('per_page', 50);
        $processes = $q->paginate($perPage)->withQueryString();

        // Si la petición es API/JSON, devolvemos el paginator tal cual
        if ($request->expectsJson() || $request->wantsJson() || $request->is('api/*')) {
            return $processes;
        }

        // Si es WEB, devolvemos la vista
        return view('processes.index', compact('processes'));
    }

    // ====== CREATE (WEB) ======
    public function create()
    {
        // Trae modelos existentes para seleccionar (uuid + name)
        $models = Modell::select('uuid', 'name')
            ->whereNotNull('uuid')
            ->orderBy('name')
            ->get();

        $defaultThreshold = 0.85;

        return view('processes.create', compact('models', 'defaultThreshold'));
    }

    // ====== STORE (WEB o API) ======
    public function store(Request $request)
    {
        // NOTA: misma validación para web y API (en web generamos el arreglo "models[...]")
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'default_threshold' => ['nullable', 'numeric', 'between:0,1'],
            'models' => ['required', 'array', 'min:1'],
            'models.*.model_uuid' => ['required', 'uuid'],
            'models.*.name' => ['nullable', 'string', 'max:255'], // opcional en web (lo resolvemos nosotros)
            'models.*.order_index' => ['required', 'integer', 'min:1'],
            'models.*.threshold' => ['nullable', 'numeric', 'between:0,1'],
            'models.*.enabled' => ['sometimes', 'boolean'],
            'payload' => ['nullable', 'array'],
            'run_at' => ['nullable', 'date'],
        ]);

        $DEFAULT_T = 0.85; // threshold fijo solicitado

        // Resolver nombres si no vienen (web): mapeamos por uuid
        $namesByUuid = Modell::whereIn('uuid', collect($data['models'])->pluck('model_uuid'))
            ->pluck('name', 'uuid');

        $models = collect($data['models'])->map(function ($m) use ($namesByUuid, $DEFAULT_T) {
            return [
                'model_uuid' => (string) $m['model_uuid'],
                'name' => $m['name'] ?? ($namesByUuid[$m['model_uuid']] ?? null),
                'order_index' => (int) $m['order_index'],
                'threshold' => isset($m['threshold']) ? (float) $m['threshold'] : $DEFAULT_T,
                'enabled' => array_key_exists('enabled', $m) ? (bool) $m['enabled'] : true,
            ];
        })->sortBy('order_index')->values()->all();

        $process = Process::create([
            'name' => $data['name'],
            'status' => 'pending',
            'payload' => $data['payload'] ?? null,
            'run_at' => $data['run_at'] ?? null,
            'default_threshold' => $data['default_threshold'] ?? $DEFAULT_T,
            'models' => $models,
            'stats' => [
                'global' => ['latency_samples' => [], 'confidence_sum' => 0, 'confidence_count' => 0],
                'by_model' => new \stdClass(),
            ],
            'total_images' => 0,
            'positives' => 0,
            'negatives' => 0,
        ]);

        // Respuesta API
        if ($request->expectsJson() || $request->wantsJson() || $request->is('api/*')) {
            return response()->json($process, Response::HTTP_CREATED);
        }

        // Respuesta WEB
        return redirect()->route('processes.index')
            ->with('success', 'Process created successfully.');
    }


    /** SHOW: GET /api/procesos/{process} */
    public function show(Process $process)
    {
        return $process;
    }

    /**
     * Web UI to run a process: single-image validation per model.
     */
    public function run(Process $process)
    {
        // For web view we pass the process instance (Eloquent json casts will be available)
        return view('processes.run', ['process' => $process]);
    }

    /** UPDATE: PUT/PATCH /api/procesos/{process} */
    public function update(Request $request, Process $process)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:pending,running,succeeded,failed,canceled'],
            'payload' => ['sometimes', 'nullable', 'array'],
            'run_at' => ['sometimes', 'nullable', 'date'],
            'started_at' => ['sometimes', 'nullable', 'date'],
            'finished_at' => ['sometimes', 'nullable', 'date'],
            'error_message' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'default_threshold' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],

            'models' => ['sometimes', 'array', 'min:1'],
            'models.*.model_uuid' => ['required_with:models', 'uuid'],
            'models.*.name' => ['nullable', 'string', 'max:255'],
            'models.*.order_index' => ['required_with:models', 'integer', 'min:1'],
            'models.*.threshold' => ['nullable', 'numeric', 'between:0,1'],
            'models.*.enabled' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('models', $data)) {
            $data['models'] = collect($data['models'])->map(function ($m) {
                return [
                    'model_uuid' => (string) $m['model_uuid'],
                    'name' => $m['name'] ?? null,
                    'order_index' => (int) $m['order_index'],
                    'threshold' => isset($m['threshold']) ? (float) $m['threshold'] : null,
                    'enabled' => array_key_exists('enabled', $m) ? (bool) $m['enabled'] : true,
                ];
            })->sortBy('order_index')->values()->all();
        }

        $process->update($data);
        return $process->refresh();
    }

    /** DELETE: DELETE /api/procesos/{process} */
    public function destroy(Process $process)
    {
        $process->delete();
        return response()->noContent();
    }

    /**
     * VALIDATE: POST /api/procesos/{process}/validate
     * Params:
     *  - image (file, required)
     *  - camera_id (string, optional)
     *  - model_uuid (uuid, optional)  -> prioridad 1
     *  - model_order (int, optional)  -> prioridad 2
     *  Si no se envía ninguno, toma el primer modelo habilitado por order_index.
     *
     * Ejemplo:
     * curl -X POST "http://127.0.0.1:8000/api/procesos/1/validate" \
     *   -H "Accept: application/json" \
     *   -F "model_uuid=7503fd1f-9a54-4bc3-af49-ecdf2c4dc282" \
     *   -F "image=@/path/to/image.jpeg" \
     *   -F "camera_id=dock-01"
     */
    public function validateAndUpdate(Request $request, Process $process)
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'max:8192'], // 8MB
            'camera_id' => ['nullable', 'string', 'max:255'],
            'model_uuid' => ['sometimes', 'uuid'],
            'model_order' => ['sometimes', 'integer', 'min:1'],
        ]);

        // 1) Guardar imagen (auditoría)
        $file = $request->file('image');
        if (!$request->hasFile('image') || !$file->isValid()) {
            return response()->json([
                'message' => 'Upload failed',
                'code' => $file?->getError(),
                'detail' => $file?->getErrorMessage(),
            ], 422);
        }

        Storage::makeDirectory("public/procesoss/{$process->id}");
        $storedPath = $file->store("public/procesos/{$process->id}");
        $absPath = Storage::path($storedPath);
        if (!is_file($absPath)) {
            Log::warning('Stored image not found; fallback to temp path', [
                'storedPath' => $storedPath,
                'absPath' => $absPath,
                'tmp' => $file->getRealPath(),
            ]);
            $absPath = $file->getRealPath();
        }

        // 2) Resolver EL ÚNICO modelo a procesar
        $models = collect($process->models ?? []);
        $target = null;

        if ($uuid = $request->input('model_uuid')) {
            $target = $models->firstWhere('model_uuid', $uuid);
        } elseif ($order = $request->input('model_order')) {
            $target = $models->firstWhere('order_index', (int) $order);
        } else {
            $target = $models->sortBy('order_index')->first(function ($m) {
                return !isset($m['enabled']) || $m['enabled'] !== false;
            });
        }

        if (!$target) {
            return response()->json([
                'message' => 'Target model not found in process',
                'errors' => ['model' => ['Invalid model_uuid/model_order or no enabled models.']],
            ], 422);
        }

        if (isset($target['enabled']) && $target['enabled'] === false) {
            // No llamamos a Flask: es el comportamiento solicitado
            $this->mergeModelStats($process, $target['model_uuid'], null, null, 0, false, 'skipped');
            $process->last_request = [
                'received_at' => now()->toISOString(),
                'image_name' => $file->getClientOriginalName(),
                'camera_id' => $request->input('camera_id'),
                'results' => [
                    [
                        'model_uuid' => $target['model_uuid'],
                        'approved' => null,
                        'confidence' => null,
                        'threshold' => $target['threshold'] ?? $process->default_threshold ?? null,
                        'latency_ms' => 0,
                        'request_id' => null,
                        'skipped' => true,
                        'reason' => 'disabled',
                    ]
                ],
            ];
            $process->total_images = (int) $process->total_images + 1;
            $process->save();

            return response()->json([
                'process' => [
                    'id' => $process->id,
                    'total_images' => $process->total_images,
                    'positives' => $process->positives,
                    'negatives' => $process->negatives,
                    'avg_confidence' => $process->avg_confidence,
                    'avg_latency_ms' => $process->avg_latency_ms,
                    'p95_latency_ms' => $process->p95_latency_ms,
                    'max_latency_ms' => $process->max_latency_ms,
                ],
                'per_model' => $this->buildPerModelSnapshot($process),
                'last_request' => $process->last_request,
            ], 409); // Conflict: el modelo está deshabilitado
        }

        $threshold = $target['threshold'] ?? $process->default_threshold ?? null;

        // 3) Cliente Guzzle - Validación simplificada
        $client = $this->inferenceClient();

        // 4) Multipart (SOLO uuid + image) — NO enviamos threshold
        // Open the file stream safely: prefer the stored absolute path, fallback to the uploaded tmp path.
        $stream = null;
        try {
            if ($absPath && is_file($absPath) && is_readable($absPath)) {
                $stream = fopen($absPath, 'r');
            } elseif ($file->getRealPath() && is_file($file->getRealPath()) && is_readable($file->getRealPath())) {
                $stream = fopen($file->getRealPath(), 'r');
            }
        } catch (\Throwable $e) {
            Log::error('Failed opening image for multipart', ['absPath' => $absPath, 'tmp' => $file->getRealPath(), 'exception' => $e->getMessage()]);
            $stream = null;
        }

        if ($stream === null) {
            Log::error('Cannot open uploaded image for sending to inference', ['absPath' => $absPath, 'tmp' => $file->getRealPath()]);
            return response()->json([
                'message' => 'Unable to read uploaded image for inference',
                'detail' => 'Check storage permissions and ensure the uploaded file is available',
            ], 500);
        }

        $multipart = [
            ['name' => 'uuid', 'contents' => $target['model_uuid']],
            [
                'name' => 'image',
                'contents' => $stream,
                'filename' => $file->getClientOriginalName(),
                'headers' => ['Content-Type' => $file->getMimeType() ?: 'application/octet-stream'],
            ],
        ];

        // 5) Llamada a Flask con medición de latencia
        $t0 = hrtime(true);
        try {
            $res = $client->post('validate', $this->inferenceRequestOptions($multipart));
        } catch (GuzzleException $e) {
            $latencyMs = (int) round((hrtime(true) - $t0) / 1e6);
            Log::warning('Inference call failed', ['exception' => $e->getMessage()]);
            $this->mergeModelStats($process, $target['model_uuid'], null, null, $latencyMs, false, 'error');

            $result = [
                'model_uuid' => $target['model_uuid'],
                'approved' => null,
                'confidence' => null,
                'threshold' => $threshold,
                'latency_ms' => $latencyMs,
                'request_id' => null,
                'error' => 'network_error: ' . $e->getMessage(),
            ];
            return $this->finalizeSingleResult($process, $file, $request->input('camera_id'), $result);
        }
        $latencyMs = (int) round((hrtime(true) - $t0) / 1e6);

        $status = $res->getStatusCode();
        $bodyRaw = (string) $res->getBody();
        $body = json_decode($bodyRaw, true);

        // 6) Manejo de errores / modelo no entrenado → skipped: untrained
        $isUntrained = false;
        $msg = strtolower((string) ($body['message'] ?? $bodyRaw));
        if (
            ($status === 404 || $status === 500) &&
            (str_contains($msg, 'artifact not found') || str_contains($msg, 'artifacts') || str_contains($msg, 'missing'))
        ) {
            $isUntrained = true;
        }

        if ($status !== 200 || !is_array($body)) {
            if ($isUntrained) {
                $this->mergeModelStats($process, $target['model_uuid'], null, null, $latencyMs, false, 'skipped');
                $result = [
                    'model_uuid' => $target['model_uuid'],
                    'approved' => null,
                    'confidence' => null,
                    'threshold' => $threshold,
                    'latency_ms' => $latencyMs,
                    'request_id' => $body['request_id'] ?? null,
                    'skipped' => true,
                    'reason' => 'untrained',
                    'error' => "HTTP {$status}",
                    'error_detail' => $body['message'] ?? substr($bodyRaw, 0, 300),
                ];
            } else {
                $this->mergeModelStats($process, $target['model_uuid'], null, null, $latencyMs, false, 'error');
                $result = [
                    'model_uuid' => $target['model_uuid'],
                    'approved' => null,
                    'confidence' => null,
                    'threshold' => $threshold,
                    'latency_ms' => $latencyMs,
                    'request_id' => $body['request_id'] ?? null,
                    'error' => "HTTP {$status}",
                    'error_detail' => $body['message'] ?? substr($bodyRaw, 0, 300),
                ];
            }
            return $this->finalizeSingleResult($process, $file, $request->input('camera_id'), $result);
        }

        // 7) Payload real: { prediction, confidence }
        $prediction = $body['prediction'] ?? null; // "approved"|"rejected"
        $approved = $prediction !== null ? ($prediction === 'approved') : (bool) ($body['approved'] ?? false);
        $confidence = isset($body['confidence']) ? (float) $body['confidence'] : null;
        $requestId = $body['request_id'] ?? null;

        if ($approved === true) {
            $process->positives += 1;
        }
        if ($approved === false) {
            $process->negatives += 1;
        }
        $this->mergeModelStats($process, $target['model_uuid'], $confidence, $approved, $latencyMs, true, null);

        $result = [
            'model_uuid' => $target['model_uuid'],
            'approved' => $approved,
            'confidence' => $confidence,
            'threshold' => $threshold,
            'latency_ms' => $latencyMs,
            'request_id' => $requestId,
        ];

        return $this->finalizeSingleResult($process, $file, $request->input('camera_id'), $result);
    }

    /** INSIGHTS: GET /api/procesos/{process}/insights */
    public function insights(Process $process)
    {
        $perModel = $this->buildPerModelSnapshot($process);

        $inferTotal = array_sum(array_map(fn($m) => $m['inferences'], $perModel));
        $posTotal = array_sum(array_map(fn($m) => $m['positives'], $perModel));
        $negTotal = array_sum(array_map(fn($m) => $m['negatives'], $perModel));

        return response()->json([
            'global' => [
                'total_images' => (int) $process->total_images,
                'inferences_total' => (int) $inferTotal,
                'positives' => (int) $posTotal,
                'negatives' => (int) $negTotal,
                'positive_rate' => $inferTotal ? round($posTotal / $inferTotal, 4) : null,
                'avg_confidence' => $process->avg_confidence,
                'avg_latency_ms' => $process->avg_latency_ms,
                'p95_latency_ms' => $process->p95_latency_ms,
                'max_latency_ms' => $process->max_latency_ms,
            ],
            'models' => $perModel,
            'last_request' => $process->last_request,
        ]);
    }

    /** ===== Helpers ===== */

    protected function buildPerModelSnapshot(Process $process): array
    {
        $models = collect($process->models ?? [])->keyBy('model_uuid');
        $by = $process->stats['by_model'] ?? [];

        $out = [];
        foreach ($by as $uuid => $m) {
            $name = optional($models->get($uuid))['name'] ?? $uuid;

            $avgConf = ($m['confidence_count'] ?? 0) > 0
                ? round($m['confidence_sum'] / $m['confidence_count'], 4)
                : null;

            $lat = $m['latency_samples'] ?? [];
            $avgLat = $p95 = $maxLat = null;
            if (!empty($lat)) {
                sort($lat);
                $avgLat = (int) round(array_sum($lat) / count($lat));
                $maxLat = (int) max($lat);
                $p95 = (int) $lat[(int) floor(0.95 * (count($lat) - 1))];
            }

            $out[] = [
                'model_uuid' => $uuid,
                'model_name' => $name,
                'order_index' => (int) ($models->get($uuid)['order_index'] ?? 0),
                'threshold' => $models->get($uuid)['threshold'] ?? null,
                'inferences' => (int) ($m['inferences'] ?? 0),
                'positives' => (int) ($m['positives'] ?? 0),
                'negatives' => (int) ($m['negatives'] ?? 0),
                'skipped' => (int) ($m['skipped'] ?? 0),
                'errors' => (int) ($m['errors'] ?? 0),
                'positive_rate' => ($m['inferences'] ?? 0) ? round(($m['positives'] ?? 0) / $m['inferences'], 4) : null,
                'avg_confidence' => $avgConf,
                'avg_latency_ms' => $avgLat,
                'p95_latency_ms' => $p95,
                'max_latency_ms' => $maxLat,
            ];
        }

        usort($out, fn($a, $b) => ($a['order_index'] <=> $b['order_index']));
        return $out;
    }

    protected function mergeModelStats(
        Process $process,
        string $modelUuid,
        ?float $confidence,
        ?bool $approved,
        ?int $latencyMs,
        bool $countAsInference = true,
        ?string $flag = null // 'skipped' | 'error' | null
    ): void {
        $stats = $process->stats ?? [];

        // Global
        $stats['global'] = $stats['global'] ?? ['latency_samples' => [], 'confidence_sum' => 0, 'confidence_count' => 0];
        if ($latencyMs !== null && $latencyMs > 0) {
            $this->pushSample($stats['global']['latency_samples'], $latencyMs, 200);
        }
        if ($confidence !== null) {
            $stats['global']['confidence_sum'] += $confidence;
            $stats['global']['confidence_count'] += 1;
        }

        // Por modelo
        $stats['by_model'] = $stats['by_model'] ?? [];
        $m = $stats['by_model'][$modelUuid] ?? [
            'inferences' => 0,
            'positives' => 0,
            'negatives' => 0,
            'confidence_sum' => 0,
            'confidence_count' => 0,
            'latency_samples' => [],
            'skipped' => 0,
            'errors' => 0,
        ];

        if ($flag === 'skipped') {
            $m['skipped']++;
        }
        if ($flag === 'error') {
            $m['errors']++;
        }

        if ($countAsInference && $approved !== null) {
            $m['inferences'] += 1;
            if ($approved === true) {
                $m['positives'] += 1;
            }
            if ($approved === false) {
                $m['negatives'] += 1;
            }
            if ($confidence !== null) {
                $m['confidence_sum'] += $confidence;
                $m['confidence_count'] += 1;
            }
        }

        if ($latencyMs !== null && $latencyMs > 0) {
            $this->pushSample($m['latency_samples'], $latencyMs, 200);
        }

        $stats['by_model'][$modelUuid] = $m;
        $process->stats = $stats;
    }

    protected function pushSample(array &$samples, int $val, int $maxLen): void
    {
        $samples[] = $val;
        if (count($samples) > $maxLen)
            array_shift($samples);
    }

    /** ==== Guzzle helpers ==== */
    private function inferenceClient(): Client
    {
        // Prefer the configured ExternalModelApi base URL so the app has a single source of truth
        try {
            /** @var ExternalModelApi $externalApi */
            $externalApi = app()->make(ExternalModelApi::class);
            $base = $externalApi->getBaseUrl();
        } catch (\Throwable $e) {
            $base = null;
        }

        if (empty($base)) {
            throw new \Exception('Validation failed: inference base URL is not set. Configure services.flask.base_url or bind ExternalModelApi.');
        }

        return new Client([
            'base_uri' => rtrim($base, '/') . '/',
            'timeout' => 30,
            'http_errors' => false,
            'verify' => false, // ngrok no necesita verificación SSL
        ]);
    }

    private function inferenceRequestOptions(array $multipart): array
    {
        return [
            'headers' => [
                'Accept' => 'application/json',
                'ngrok-skip-browser-warning' => 'true', // evita la página de advertencia de ngrok
            ],
            'multipart' => $multipart
        ];
    }

    /** Finaliza guardando last_request y agregados para un solo resultado */
    private function finalizeSingleResult(Process $process, \Illuminate\Http\UploadedFile $file, ?string $cameraId, array $result)
    {
        // Contamos la imagen a nivel proceso (si prefieres no contar en errores, puedes condicionarlo)
        $process->total_images = (int) $process->total_images + 1;

        // Recalcular agregados globales
        $stats = $process->stats ?? [];
        $g = $stats['global'] ?? ['latency_samples' => [], 'confidence_sum' => 0, 'confidence_count' => 0];

        $process->avg_confidence = $g['confidence_count'] > 0
            ? round($g['confidence_sum'] / $g['confidence_count'], 4)
            : null;

        $lat = $g['latency_samples'] ?? [];
        if (!empty($lat)) {
            sort($lat);
            $n = count($lat);
            $process->avg_latency_ms = (int) round(array_sum($lat) / $n);
            $process->max_latency_ms = (int) max($lat);
            $process->p95_latency_ms = (int) $lat[(int) floor(0.95 * ($n - 1))];
        } else {
            $process->avg_latency_ms = $process->max_latency_ms = $process->p95_latency_ms = null;
        }

        $process->last_request = [
            'received_at' => now()->toISOString(),
            'image_name' => $file->getClientOriginalName(),
            'camera_id' => $cameraId,
            'results' => [$result],
        ];

        $process->save();

        return response()->json([
            'process' => [
                'id' => $process->id,
                'total_images' => $process->total_images,
                'positives' => $process->positives,
                'negatives' => $process->negatives,
                'avg_confidence' => $process->avg_confidence,
                'avg_latency_ms' => $process->avg_latency_ms,
                'p95_latency_ms' => $process->p95_latency_ms,
                'max_latency_ms' => $process->max_latency_ms,
            ],
            'per_model' => $this->buildPerModelSnapshot($process),
            'last_request' => $process->last_request,
        ]);
    }
}
