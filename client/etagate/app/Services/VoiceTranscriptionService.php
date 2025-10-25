<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VoiceTranscriptionService
{
    protected $geminiApiKey;

    public function __construct()
    {
        $this->geminiApiKey = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
    }

    /**
     * Transcribe audio usando la Web Speech API del navegador (procesamiento local)
     * o servicios alternativos si es necesario
     */
    public function transcribeAudio(string $audioPath): ?string
    {
        try {
            // Por ahora, como Gemini no tiene API de transcripción directa,
            // vamos a usar una estrategia híbrida:
            // 1. El navegador hará la transcripción inicial usando Web Speech API
            // 2. Este servicio puede procesar/mejorar el texto si es necesario
            
            // Para la implementación inicial, retornamos null para que el frontend
            // maneje la transcripción directamente con Web Speech API
            
            Log::info('Transcripción solicitada para archivo: ' . $audioPath);
            
            // En futuras versiones, aquí podríamos:
            // - Usar OpenAI Whisper API
            // - Usar Google Speech-to-Text
            // - Usar Azure Speech Services
            // - Procesar con FFmpeg para mejorar calidad de audio
            
            return null;

        } catch (\Exception $e) {
            Log::error('Error en VoiceTranscriptionService::transcribeAudio: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mejora la transcripción usando Gemini AI (corrección de texto)
     */
    public function improveTranscription(string $rawTranscription): ?string
    {
        try {
            if (empty($this->geminiApiKey) || empty($rawTranscription)) {
                return $rawTranscription;
            }

            $response = Http::timeout(15)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent", [
                    'key' => $this->geminiApiKey,
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $this->buildTranscriptionImprovementPrompt($rawTranscription)
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1, // Baja temperatura para mayor precisión
                        'topK' => 20,
                        'topP' => 0.8,
                        'maxOutputTokens' => 512,
                    ]
                ]);

            if (!$response->successful()) {
                Log::warning('Error mejorando transcripción con Gemini, devolviendo original');
                return $rawTranscription;
            }

            $data = $response->json();
            
            if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $rawTranscription;
            }

            $improvedText = trim($data['candidates'][0]['content']['parts'][0]['text']);
            
            // Verificar que la mejora sea válida (no esté vacía ni sea muy diferente)
            if (empty($improvedText) || strlen($improvedText) < strlen($rawTranscription) * 0.5) {
                return $rawTranscription;
            }

            return $improvedText;

        } catch (\Exception $e) {
            Log::error('Error en VoiceTranscriptionService::improveTranscription: ' . $e->getMessage());
            return $rawTranscription;
        }
    }

    /**
     * Construye el prompt para mejorar la transcripción
     */
    protected function buildTranscriptionImprovementPrompt(string $transcription): string
    {
        return "Por favor, mejora la siguiente transcripción de voz corrigiendo errores de ortografía, puntuación y gramática, pero manteniendo el significado original exacto. 

Reglas importantes:
- No cambies el sentido ni añadas información nueva
- Corrige solo errores evidentes de transcripción automática
- Mantén el estilo coloquial si es apropiado
- Agrega puntuación correcta
- Si no hay errores evidentes, devuelve el texto original

Transcripción original:
\"$transcription\"

Transcripción mejorada:";
    }

    /**
     * Valida la calidad del audio para transcripción
     */
    public function validateAudioQuality(string $audioPath): array
    {
        try {
            if (!file_exists($audioPath)) {
                return [
                    'valid' => false,
                    'error' => 'Archivo de audio no encontrado'
                ];
            }

            $fileSize = filesize($audioPath);
            
            // Validaciones básicas
            if ($fileSize < 1024) { // Menos de 1KB
                return [
                    'valid' => false,
                    'error' => 'Archivo de audio demasiado pequeño'
                ];
            }

            if ($fileSize > 10 * 1024 * 1024) { // Más de 10MB
                return [
                    'valid' => false,
                    'error' => 'Archivo de audio demasiado grande (máximo 10MB)'
                ];
            }

            // Verificar tipo MIME (básico)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $audioPath);
            finfo_close($finfo);

            $allowedMimes = ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/webm'];
            
            if (!in_array($mimeType, $allowedMimes)) {
                return [
                    'valid' => false,
                    'error' => 'Tipo de archivo no soportado: ' . $mimeType
                ];
            }

            return [
                'valid' => true,
                'size' => $fileSize,
                'mime_type' => $mimeType
            ];

        } catch (\Exception $e) {
            Log::error('Error validando calidad de audio: ' . $e->getMessage());
            return [
                'valid' => false,
                'error' => 'Error validando archivo de audio'
            ];
        }
    }

    /**
     * Limpia archivos de audio temporales
     */
    public function cleanupTempAudio(int $olderThanHours = 1): int
    {
        try {
            $tempFiles = Storage::disk('local')->files('temp_audio');
            $deletedCount = 0;
            $cutoffTime = time() - ($olderThanHours * 3600);

            foreach ($tempFiles as $file) {
                $lastModified = Storage::disk('local')->lastModified($file);
                if ($lastModified < $cutoffTime) {
                    Storage::disk('local')->delete($file);
                    $deletedCount++;
                }
            }

            return $deletedCount;

        } catch (\Exception $e) {
            Log::error('Error limpiando archivos temporales: ' . $e->getMessage());
            return 0;
        }
    }
}