<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Process as ProcessModel;

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
        $processesJson = $this->buildProcessesJson();

        return "You are an intelligent and helpful virtual assistant called EtaGate Assistant.
        Your purpose is to help users with their queries in a clear, concise and friendly manner.

        Important characteristics:
        - Respond ALWAYS in English
        - Be concise but informative (maximum 2-3 sentences)
        - Maintain a professional but friendly tone
        - If you don't know something, admit it honestly
        - Focus on being useful and solving problems
        - For long responses, structure with clear points

        Context: You are in the etagate platform, a business process management system.
        Users can ask you about system functionalities, processes, models, etc.

        In some cases, users may ask to run a process. You can identify those requests by the usage of keywords like run, execute, start process, initiate workflow, etc.
        When you detect such a request, respond with a JSON object containing the process name and parameters, like this:
        {
            message_user: (string) A brief confirmation message for the user,
            uuid_process: (string) The unique identifier of the process to run,

        }
            In order to identify wich process to run, you have access to the following list of processes:\n\n"
            . "AVAILABLE_PROCESSES_JSON:\n" . $processesJson . "\n\n"

            

            . "CRITICAL: All responses must be in English, regardless of the language the user speaks to you.";
    }

    /**
     * Build a JSON array with { id, name, description } for every stored process.
     * If a process has no explicit description, tries to read it from payload['description'].
     * Returns a compact JSON string safe to concatenate into prompts.
     */
    protected function buildProcessesJson(): string
    {
        try {
            $rows = ProcessModel::all();
            $out = $rows->map(function ($p) {
                $desc = '';
                if (isset($p->description) && $p->description !== null) {
                    $desc = (string) $p->description;
                } elseif (is_array($p->payload) && array_key_exists('description', $p->payload)) {
                    $desc = (string) ($p->payload['description'] ?? '');
                }

                return [
                    'id' => $p->id ?? null,
                    'name' => (string) ($p->name ?? ''),
                    'description' => $desc,
                ];
            })->toArray();

            return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Exception $e) {
            Log::error('Could not build processes JSON: ' . $e->getMessage());
            return '[]';
        }
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
