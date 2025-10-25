<?php

namespace App\Http\Controllers;

use App\Services\GeminiAiService;
use App\Services\ElevenLabsService;
use App\Services\VoiceTranscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VoiceAssistantController extends Controller
{
    protected $geminiService;
    protected $elevenLabsService;
    protected $voiceTranscriptionService;

    public function __construct(
        GeminiAiService $geminiService,
        ElevenLabsService $elevenLabsService,
        VoiceTranscriptionService $voiceTranscriptionService
    ) {
        $this->geminiService = $geminiService;
        $this->elevenLabsService = $elevenLabsService;
        $this->voiceTranscriptionService = $voiceTranscriptionService;
    }

    /**
     * Procesa texto del usuario transcrito en el frontend, genera respuesta y síntesis de voz
     */
    public function processTextMessage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'message' => 'required|string|max:1000',
            ]);

            $userMessage = trim($request->input('message'));

            if (empty($userMessage)) {
                return response()->json([
                    'success' => false,
                    'error' => 'El mensaje no puede estar vacío'
                ], 400);
            }

            // 1. Procesar con Gemini AI para generar respuesta
            $aiResponse = $this->geminiService->generateResponse($userMessage);
            
            if (!$aiResponse) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo generar respuesta con Gemini'
                ], 500);
            }

            // 2. Generar audio de respuesta con ElevenLabs
            $responseAudioPath = $this->elevenLabsService->generateSpeech($aiResponse);
            
            if (!$responseAudioPath) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo generar síntesis de voz'
                ], 500);
            }

            // 3. Convertir audio de respuesta a base64 para envío al frontend
            $audioBase64 = base64_encode(Storage::disk('local')->get($responseAudioPath));

            // 4. Limpiar archivo de audio después de un tiempo
            dispatch(function () use ($responseAudioPath) {
                sleep(300); // 5 minutos
                Storage::disk('local')->delete($responseAudioPath);
            })->delay(now()->addMinutes(5));

            return response()->json([
                'success' => true,
                'data' => [
                    'user_message' => $userMessage,
                    'assistant_message' => $aiResponse,
                    'audio_response' => $audioBase64,
                    'audio_mime_type' => 'audio/mpeg'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en VoiceAssistantController::processTextMessage: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtiene el historial de conversaciones del asistente de voz
     */
    public function getConversationHistory(Request $request): JsonResponse
    {
        try {
            // Por ahora devolvemos un array vacío, en el futuro se puede implementar
            // almacenamiento en base de datos de las conversaciones
            return response()->json([
                'success' => true,
                'data' => [
                    'conversations' => []
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en VoiceAssistantController::getConversationHistory: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Limpia archivos temporales de audio antiguos
     */
    public function cleanupTempAudio(): JsonResponse
    {
        try {
            $tempAudioFiles = Storage::disk('local')->files('temp_audio');
            $tempResponseFiles = Storage::disk('local')->files('voice_responses');
            
            $deletedCount = 0;
            
            // Eliminar archivos temporales más antiguos de 1 hora
            foreach (array_merge($tempAudioFiles, $tempResponseFiles) as $file) {
                $fileTime = Storage::disk('local')->lastModified($file);
                if (time() - $fileTime > 3600) { // 1 hora
                    Storage::disk('local')->delete($file);
                    $deletedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'deleted_files' => $deletedCount
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en VoiceAssistantController::cleanupTempAudio: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ], 500);
        }
    }
}