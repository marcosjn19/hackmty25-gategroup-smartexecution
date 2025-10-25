<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VoiceAssistantController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('layouts.app');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// API Routes
Route::prefix('api')->group(function () {
    
    // User endpoint con autenticación Sanctum
    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });

    // Endpoint de health check (sin auth para diagnóstico)
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

    // Rutas del asistente de voz (SIN AUTENTICACIÓN)
    Route::prefix('voice-assistant')->group(function () {
        // Procesar mensaje de texto transcrito en el frontend
        Route::post('/process-text', [VoiceAssistantController::class, 'processTextMessage'])
            ->middleware(['throttle:20,1'])
            ->name('voice-assistant.process-text');
        
        // Procesar mensaje de voz completo (LEGACY - redirige a process-text)
        Route::post('/process', [VoiceAssistantController::class, 'processTextMessage'])
            ->middleware(['throttle:20,1'])
            ->name('voice-assistant.process');
        
        // Obtener historial de conversaciones
        Route::get('/history', [VoiceAssistantController::class, 'getConversationHistory'])
            ->name('voice-assistant.history');
        
        // Limpiar archivos temporales de audio
        Route::post('/cleanup', [VoiceAssistantController::class, 'cleanupTempAudio'])
            ->name('voice-assistant.cleanup');
    });
});