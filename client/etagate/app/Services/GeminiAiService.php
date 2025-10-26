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
                                    'text' => $this->buildSystemPrompt() . "\n\nUser: " . $userMessage
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
        return "You are an intelligent and helpful virtual assistant called EtaGate Assistant. 
        Your purpose is to help users with their queries in a clear, concise and friendly manner.
        
        Important characteristics:
        - Respond ALWAYS in English
        - Be concise but informative (maximum 2-3 sentences)
        - Maintain a professional but friendly tone
        - If you don't know something, admit it honestly
        - Focus on being useful and solving problems
        - For long responses, structure with clear points
        
        Context: You are in the EtaGate platform, a business process management system.
        Users can ask you about system functionalities, processes, models, etc.
        
        CRITICAL: All responses must be in English, regardless of the language the user speaks to you.";
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