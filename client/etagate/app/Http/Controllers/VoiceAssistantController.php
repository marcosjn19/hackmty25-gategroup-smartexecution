<?php

namespace App\Http\Controllers;

use App\Services\GeminiAiService;
use App\Services\ElevenLabsService;
use App\Services\VoiceTranscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Process as ProcessModel;

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
     * Try to extract a JSON object from arbitrary text (AI response).
     * Returns associative array or null.
     */
    protected function extractJsonInstruction(string $text): ?array
    {
        if (empty($text)) return null;

        // First quick attempt: find the first { and last } and decode
        $first = strpos($text, '{');
        $last = strrpos($text, '}');
        if ($first !== false && $last !== false && $last > $first) {
            $sub = substr($text, $first, $last - $first + 1);
            $decoded = json_decode($sub, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Fallback: try to find any {...} groups with a regex and decode them
        if (preg_match_all('/\{[^}]*\}/s', $text, $matches)) {
            foreach ($matches[0] as $m) {
                $decoded = json_decode($m, true);
                if (is_array($decoded)) return $decoded;
            }
        }

        return null;
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


            // 2. Intentar detectar instrucciones estructuradas en la respuesta (ej. ejecutar proceso)
            $processTrigger = null;
            try {
                $instr = $this->extractJsonInstruction($aiResponse);
                if (is_array($instr)) {
                    // posibles campos: id, process_id, process_name, name
                    $procId = $instr['id'] ?? $instr['process_id'] ?? null;
                    $procName = $instr['process_name'] ?? $instr['name'] ?? null;

                    $found = null;
                    if ($procId) {
                        $found = ProcessModel::find($procId);
                    }
                    if (!$found && $procName) {
                        $found = ProcessModel::where('name', $procName)->first();
                    }

                    if ($found) {
                        // Duplicate the process as a new run (simple approach)
                        $run = ProcessModel::create([
                            'name' => $found->name . ' (invocation)',
                            'status' => 'pending',
                            'payload' => $found->payload,
                            'run_at' => now(),
                            'default_threshold' => $found->default_threshold,
                            'models' => $found->models,
                            'stats' => $found->stats,
                            'total_images' => 0,
                            'positives' => 0,
                            'negatives' => 0,
                        ]);

                        $processTrigger = [
                            'ok' => true,
                            'invoked_process_id' => $run->id,
                            'invoked_process_name' => $run->name,
                        ];
                    } else {
                        if ($procId || $procName) {
                            $processTrigger = [
                                'ok' => false,
                                'error' => 'Process not found',
                                'requested' => ['id' => $procId, 'name' => $procName]
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to parse instruction from AI: ' . $e->getMessage());
            }

            // 3. Generar audio de respuesta con ElevenLabs
            $responseAudioPath = $this->elevenLabsService->generateSpeech($aiResponse);

            if (!$responseAudioPath) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo generar síntesis de voz'
                ], 500);
            }

            //2.5 En caso de que se quiera ejecutar un processs se realice aquí



            // 3. Convertir audio de respuesta a base64 para envío al frontend
            $audioBase64 = base64_encode(Storage::disk('local')->get($responseAudioPath));

            // 4. Limpiar archivo de audio después de un tiempo
            dispatch(function () use ($responseAudioPath) {
                sleep(300); // 5 minutos
                Storage::disk('local')->delete($responseAudioPath);
            })->delay(now()->addMinutes(5));



            $resp = [
                'success' => true,
                'data' => [
                    'user_message' => $userMessage,
                    'assistant_message' => $aiResponse,
                    'audio_response' => $audioBase64,
                    'audio_mime_type' => 'audio/mpeg'
                ]
            ];

            if ($processTrigger !== null) {
                $resp['data']['process_trigger'] = $processTrigger;
            }

            return response()->json($resp);

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
