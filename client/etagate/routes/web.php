<?php

use App\Http\Controllers\ModelController;
use App\Http\Controllers\VoiceAssistantController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\ProcessController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

// Home → Dashboard público
Route::get('/', fn() => redirect()->route('dashboard'));

// Dashboard público
Route::get('/dashboard', fn() => view('dashboard'))->name('dashboard');

// POST real
Route::post('/models/{model}/samples', [ModelController::class, 'storeSamples'])
    ->name('models.samples.store');

// Preflight (si llega), responde 204 y listo — SIN nombre
Route::options('/models/{model}/samples', fn() => response()->noContent());

// Models (público)
Route::resource('models', ModelController::class)->only(['index', 'create', 'store', 'destroy']);

// API Routes
Route::prefix('api')->group(function () {
    // User endpoint con autenticación Sanctum
    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });

    // Endpoint de health check (sin auth para diagnóstico)
    Route::get('/health', fn() => response()->json([
        'success' => true,
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'services' => [
            'database' => true,
            'gemini' => !empty(env('GEMINI_API_KEY')),
            'elevenlabs' => !empty(env('ELEVENLABS_API_KEY'))
        ]
    ]))->name('api.health');

    // Rutas del asistente de voz (SIN AUTENTICACIÓN)
    Route::prefix('voice-assistant')->group(function () {
        // Procesar mensaje de texto transcrito en el frontend
        Route::post('/process-text', [VoiceAssistantController::class, 'processTextMessage'])->middleware(['throttle:20,1'])->name('voice-assistant.process-text');

        // Procesar mensaje de voz completo (LEGACY - redirige a process-text)
        Route::post('/process', [VoiceAssistantController::class, 'processTextMessage'])->middleware(['throttle:20,1'])->name('voice-assistant.process');

        // Obtener historial de conversaciones
        Route::get('/history', [VoiceAssistantController::class, 'getConversationHistory'])->name('voice-assistant.history');

        // Limpiar archivos temporales de audio
        Route::post('/cleanup', [VoiceAssistantController::class, 'cleanupTempAudio'])->name('voice-assistant.cleanup');
    });
});

// Route::post('/models/{model}/samples', [ModelController::class, 'storeSamples'])->name('models.samples.store');
Route::post('/models/{model}/train', [ModelController::class, 'train'])->name('models.train');

Route::get('/processes', [ProcessController::class, 'index'])->name('processes.index');
Route::get('/processes/create', [ProcessController::class, 'create'])->name('processes.create');
Route::post('/processes', [ProcessController::class, 'store'])->name('processes.store');

// === API pública para Processes (sin auth, sin CSRF) ===
Route::prefix('api')->middleware('api')->withoutMiddleware([VerifyCsrfToken::class])->group(function () {
    Route::apiResource('procesos', ProcessController::class);
    Route::get('procesos/{process}/insights', [ProcessController::class, 'insights']);
    Route::post('procesos/{process}/validate', [ProcessController::class, 'validateAndUpdate']);

    Route::get('probe/php-ini', fn() => response()->json([
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
        'user_ini_filename' => ini_get('user_ini.filename'),
        'sapi' => php_sapi_name(),
    ]));
});

// Web UI: run (execute) a process — single-image validation flow
Route::get('/processes/{process}/run', [ProcessController::class, 'run'])->name('processes.run');



// En routes/web.php o routes/api.php
Route::get('/test-env', function() {
    return response()->json([
        'INFERENCE_API_BASE' => env('INFERENCE_API_BASE'),
        'is_empty' => empty(env('INFERENCE_API_BASE')),
        'config_app_env' => config('app.env'),
    ]);
});
