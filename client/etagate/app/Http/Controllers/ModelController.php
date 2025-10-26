<?php

namespace App\Http\Controllers;

use App\Models\Modell;
use App\Services\ExternalModelApi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ModelController extends Controller
{
    public function index(ExternalModelApi $api): View
    {
        $models = Modell::orderByDesc('created_at')->paginate(12);

        $remote = [];
        try {
            $remote = $api->availableMap();

            foreach ($remote as $uuid => $row) {
                if (!Modell::where('uuid', $uuid)->exists()) {
                    Modell::create([
                        'uuid'        => $uuid,
                        'name'        => $row['name'] ?? '',
                        'description' => $row['description'] ?? '',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $remote = [];
        }

        return view('models.index', [
            'models' => $models,
            'remote' => $remote,
        ]);
    }

    public function create(): View
    {
        return view('models.create');
    }

    public function store(Request $request, ExternalModelApi $api)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $uuid = $api->register($data['name'], $data['description'] ?? null);

        $model              = new Modell();
        $model->uuid        = $uuid;
        $model->name        = $data['name'];
        $model->description = $data['description'] ?? '';
        $model->save();

        return redirect()->route('models.index')->with('success', 'Model created successfully.');
    }

    /**
     * Subida 1x1 (XHR) o clásica. Siempre captura errores y responde claro.
     */
    public function storeSamples(Request $request, Modell $model, ExternalModelApi $api)
    {
        if (empty($model->uuid)) {
            $msg = 'This model is not registered in the external API yet.';
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'error' => $msg], 422)
                : back()->withErrors($msg);
        }

        // XHR / AJAX (multipart) – lo que envía el JS
        if ($request->expectsJson() || $request->ajax()) {
            Log::info('storeSamples[XHR]: start', [
                'method'      => $request->method(),
                'contentType' => $request->header('Content-Type'),
                'accept'      => $request->header('Accept'),
                'has_image'   => $request->hasFile('image'),
                'type'        => $request->input('type'),
            ]);

            $type = $request->input('type');
            $file = $request->file('image'); // <<< EL CAMPO ES 'image'

            if (!in_array($type, ['positive', 'negative'], true)) {
                Log::warning('storeSamples[XHR]: invalid type', ['type' => $type]);
                return response()->json(['ok' => false, 'error' => 'Invalid type.'], 422);
            }

            if (!$file || !$file->isValid()) {
                Log::warning('storeSamples[XHR]: no file or invalid', [
                    'present' => (bool) $file,
                    'error'   => $file?->getErrorMessage(),
                ]);
                return response()->json(['ok' => false, 'error' => 'Please attach an image.' . $file->getErrorMessage()], 422);
            }

            // Usa el MIME que reporta el cliente (no activa Fileinfo)
            $clientMime = (string) $file->getClientMimeType();
            if (strpos($clientMime, 'image/') !== 0) {
                Log::warning('storeSamples[XHR]: not image', ['client_mime' => $clientMime]);
                return response()->json(['ok' => false, 'error' => 'File must be an image. Client Mime'], 422);
            }

            if ($file->getSize() > 10 * 1024 * 1024) {
                Log::warning('storeSamples[XHR]: file >10MB', ['size' => $file->getSize()]);
                return response()->json(['ok' => false, 'error' => 'Image exceeds 10MB limit.'], 422);
            }

            try {
                $resp = $api->uploadSample($model->uuid, $type, $file);
                Log::info('storeSamples[XHR]: uploaded ok', ['api' => $resp]);
            } catch (\Throwable $e) {
                Log::error('storeSamples[XHR]: upload exception', [
                    'message' => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);
                // 422 para que el front marque el item como fallido pero continúe con los demás
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
            }

            return response()->json(['ok' => true]);
        }

        // Fallback: form clásico (no-JS)
        $validated = $request->validate([
            'type'      => ['required', 'in:positive,negative'],
            'image'     => ['nullable', 'image', 'max:10240'], // 10MB
            'images'    => ['nullable', 'array'],
            'images.*'  => ['image', 'max:10240'],
        ]);

        $type  = $validated['type'];
        $files = $request->hasFile('image')
            ? [$request->file('image')]
            : ($request->file('images') ?? []);

        if (!$files) {
            return back()->withErrors('Please select at least one image.');
        }

        try {
            $api->uploadSamples($model->uuid, $type, $files);
        } catch (\Throwable $e) {
            Log::error('storeSamples[FORM]: upload exception', ['message' => $e->getMessage()]);
            return back()->withErrors($e->getMessage());
        }

        return back()->with('success', 'Samples uploaded successfully.');
    }

    /** Regla: negativos >= 90% de positivos */
    public function train(Modell $model, ExternalModelApi $api)
    {
        if (empty($model->uuid)) {
            return back()->withErrors('This model is not registered in the external API yet.');
        }

        try {
            $counts = $api->counts($model->uuid);
        } catch (\Throwable $e) {
            return back()->withErrors('Could not read sample counts: ' . $e->getMessage());
        }

        $pos = max(0, (int) ($counts['positive'] ?? 0));
        $neg = max(0, (int) ($counts['negative'] ?? 0));

        if ($pos < 1 || $neg < 1) {
            return back()->withErrors('Need at least 1 positive and 1 negative sample to train.');
        }

        if (($neg * 100) < ($pos * 90)) {
            $requiredNeg = (int) ceil($pos * 0.90);
            $missing     = max(0, $requiredNeg - $neg);
            return back()->withErrors("Not enough negative samples: need at least {$requiredNeg} negatives (90% of {$pos} positives). Missing {$missing}.");
        }

        try {
            $api->train($model->uuid);
        } catch (\Throwable $e) {
            return back()->withErrors($e->getMessage());
        }

        return back()->with('success', 'Training started. It runs in background.');
    }

    public function destroy(Modell $model)
    {
        $model->delete();
        return redirect()->route('models.index')->with('success', 'Model deleted.');
    }

    // ----------------- helpers -----------------

    private function errorResponse(Request $request, string $msg, int $status)
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => false, 'error' => $msg], $status);
        }
        return back()->withErrors($msg);
    }
}
