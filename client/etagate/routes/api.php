<?php

use App\Http\Controllers\VoiceAssistantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Endpoint de health check para verificar estado de la API (sin auth para diagnóstico)
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'services' => [
            'database' => true,
            'gemini' => !empty(env('GEMINI_API_KEY')),
            'elevenlabs' => !empty(env('ELEVENLABS_API_KEY'))
        ]
    ]);
})->name('api.health');

// Rutas del asistente de voz
Route::prefix('voice-assistant')->group(function () {
    // Procesar mensaje de texto transcrito en el frontend
    Route::post('/process-text', [VoiceAssistantController::class, 'processTextMessage'])
        ->middleware(['auth', 'throttle:20,1'])
        ->name('voice-assistant.process-text');
    
    // Procesar mensaje de voz completo (LEGACY - redirige a process-text)
    Route::post('/process', [VoiceAssistantController::class, 'processTextMessage'])
        ->middleware(['auth', 'throttle:20,1'])
        ->name('voice-assistant.process');
    
    // Obtener historial de conversaciones
    Route::get('/history', [VoiceAssistantController::class, 'getConversationHistory'])
        ->middleware(['auth'])
        ->name('voice-assistant.history');
    
    // Limpiar archivos temporales de audio
    Route::post('/cleanup', [VoiceAssistantController::class, 'cleanupTempAudio'])
        ->middleware(['auth'])
        ->name('voice-assistant.cleanup');
});