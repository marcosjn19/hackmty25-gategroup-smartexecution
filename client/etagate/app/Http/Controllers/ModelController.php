<?php

namespace App\Http\Controllers;

use App\Models\Modell;
use App\Services\ExternalModelApi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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
                        'uuid' => $uuid,
                        'name' => $row['name'] ?? '',
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $uuid = $api->register($data['name'], $data['description'] ?? null);

        $model = new Modell();
        $model->uuid = $uuid;
        $model->name = $data['name'];
        $model->description = $data['description'] ?? '';
        $model->save();

        return redirect()->route('models.index')->with('success', 'Model created successfully.');
    }

    public function storeSamples(Request $request, Modell $model, ExternalModelApi $api)
    {
        if (empty($model->uuid)) {
            return back()->withErrors("This model is not registered in the external API yet.");
        }

        $validated = $request->validate([
            'type' => ['required', 'in:positive,negative'],
            'image' => ['nullable', 'image'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image'],
        ]);

        $type = $validated['type'];
        $files = [];

        if ($request->hasFile('images')) {
            $files = $request->file('images');
        } elseif ($request->hasFile('image')) {
            $files = [$request->file('image')];
        } else {
            return back()->withErrors('Please select at least one image.');
        }

        try {
            $api->uploadSamples($model->uuid, $type, $files);
        } catch (\Throwable $e) {
            return back()->withErrors($e->getMessage());
        }

        return back()->with('success', 'Samples uploaded successfully.');
    }

    /** NUEVO: inicia entrenamiento si cumple 90% de negativos vs positivos */
    public function train(Modell $model, ExternalModelApi $api)
    {
        if (empty($model->uuid)) {
            return back()->withErrors('This model is not registered in the external API yet.');
        }

        try {
            $counts = $api->counts($model->uuid); // {positive, negative}
        } catch (\Throwable $e) {
            return back()->withErrors('Could not read sample counts: ' . $e->getMessage());
        }

        $pos = max(0, (int) ($counts['positive'] ?? 0));
        $neg = max(0, (int) ($counts['negative'] ?? 0));

        // Requisito mínimo de la API
        if ($pos < 1 || $neg < 1) {
            return back()->withErrors('Need at least 1 positive and 1 negative sample to train.');
        }

        // Validación robusta de 90% usando enteros (evita redondeos con floats)
        // Se cumple si neg/pos >= 0.90  <=>  neg*100 >= pos*90
        $hasEnoughNeg = ($neg * 100) >= ($pos * 90);

        if (!$hasEnoughNeg) {
            // Umbral requerido con CEIL (p.ej., 15 pos => ceil(13.5)=14)
            $requiredNeg = (int) ceil($pos * 0.90);
            $missing = max(0, $requiredNeg - $neg);

            return back()->withErrors(
                "Not enough negative samples: need at least {$requiredNeg} negatives (90% of {$pos} positives). Missing {$missing}."
            );
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
}
