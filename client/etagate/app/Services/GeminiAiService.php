<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAiService
{
    protected $apiKey;
    protected $baseUrl;
 
    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    }

    /**
     * Genera respuesta usando Gemini AI basada en el texto del usuario
     */
    public function generateResponse(string $userMessage): ?string
    {
        try {
            if (empty($this->apiKey)) {
                Log::error('Gemini API key no configurada');
                return null;
            }

            // Usar gemini-2.5-flash (versión estable)
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/models/gemini-2.5-flash:generateContent?key={$this->apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $this->buildSystemPrompt() . "\n\nUsuario: " . $userMessage
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'topK' => 40,
                        'topP' => 0.95,
                        'maxOutputTokens' => 1024,
                    ],
                    'safetySettings' => [
                        [
                            'category' => 'HARM_CATEGORY_HARASSMENT',
                            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                        ],
                        [
                            'category' => 'HARM_CATEGORY_HATE_SPEECH',
                            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                        ],
                        [
                            'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                        ],
                        [
                            'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                        ]
                    ]
                ]);

            if (!$response->successful()) {
                Log::error('Error en Gemini API: ' . $response->body());
                return null;
            }

            $data = $response->json();
            
            if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                Log::error('Respuesta inesperada de Gemini API: ' . json_encode($data));
                return null;
            }

            return trim($data['candidates'][0]['content']['parts'][0]['text']);

        } catch (\Exception $e) {
            Log::error('Error en GeminiAiService::generateResponse: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Transcribe audio usando Gemini (si soporta transcripción)
     * Nota: Actualmente Gemini no tiene API de transcripción, esto sería para futuro
     */
    public function transcribeAudio(string $audioPath): ?string
    {
        // Por ahora usaremos un servicio alternativo en VoiceTranscriptionService
        // Este método queda preparado para cuando Gemini tenga transcripción
        Log::info('Transcripción de audio solicitada, usando servicio alternativo');
        return null;
    }

    /**
     * Construye el prompt del sistema para el asistente de voz
     */
    protected function buildSystemPrompt(): string
    {
        return "Eres un asistente virtual inteligente y útil llamado EtaGate Assistant. 
        Tu propósito es ayudar a los usuarios con sus consultas de manera clara, concisa y amigable.
        
        Características importantes:
        - Responde en español mexicano
        - Sé conciso pero informativo (máximo 2-3 oraciones)
        - Mantén un tono profesional pero amigable
        - Si no sabes algo, admítelo honestamente
        - Enfócate en ser útil y resolver problemas
        - Para respuestas largas, estructura con puntos claros
        
        Contexto: Estás en la plataforma EtaGate, un sistema de gestión de procesos empresariales.
        Los usuarios pueden preguntarte sobre funcionalidades del sistema, procesos, modelos, etc.";
    }

    /**
     * Verifica si la API de Gemini está disponible
     */
    public function isAvailable(): bool
    {
        try {
            if (empty($this->apiKey)) {
                return false;
            }

            // Usar gemini-2.5-flash (versión estable)
            $response = Http::timeout(10)
                ->get("{$this->baseUrl}/models/gemini-2.5-flash?key={$this->apiKey}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Error verificando disponibilidad de Gemini: ' . $e->getMessage());
            return false;
        }
    }
}