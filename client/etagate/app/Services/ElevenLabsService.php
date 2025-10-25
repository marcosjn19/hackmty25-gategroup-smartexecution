<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ElevenLabsService
{
    protected $apiKey;
    protected $baseUrl;
    protected $voiceId;

    public function __construct()
    {
        $this->apiKey = config('services.elevenlabs.api_key') ?? env('ELEVENLABS_API_KEY');
        $this->baseUrl = 'https://api.elevenlabs.io/v1';
        // Voice ID por defecto (Rachel - voz femenina en inglés/español)
        $this->voiceId = config('services.elevenlabs.voice_id') ?? 'flq6f7yk4E4fJM5XTYuZ';
    }

    /**
     * Genera síntesis de voz usando ElevenLabs
     */
    public function generateSpeech(string $text): ?string
    {
        try {
            if (empty($this->apiKey)) {
                Log::error('ElevenLabs API key no configurada');
                return null;
            }

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'audio/mpeg',
                    'xi-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/text-to-speech/{$this->voiceId}", [
                    'text' => $text,
                    'model_id' => 'eleven_multilingual_v2', // Soporte para español
                    'voice_settings' => [
                        'stability' => 0.5,
                        'similarity_boost' => 0.8,
                        'style' => 0.0,
                        'use_speaker_boost' => true
                    ]
                ]);

            if (!$response->successful()) {
                Log::error('Error en ElevenLabs API: ' . $response->body());
                return null;
            }

            // Guardar el audio generado
            $audioContent = $response->body();
            $fileName = 'voice_responses/response_' . time() . '_' . uniqid() . '.mp3';
            
            Storage::disk('local')->put($fileName, $audioContent);

            return $fileName;

        } catch (\Exception $e) {
            Log::error('Error en ElevenLabsService::generateSpeech: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene la lista de voces disponibles
     */
    public function getAvailableVoices(): ?array
    {
        try {
            if (empty($this->apiKey)) {
                Log::error('ElevenLabs API key no configurada');
                return null;
            }

            $response = Http::timeout(15)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'xi-api-key' => $this->apiKey,
                ])
                ->get("{$this->baseUrl}/voices");

            if (!$response->successful()) {
                Log::error('Error obteniendo voces de ElevenLabs: ' . $response->body());
                return null;
            }

            $data = $response->json();
            return $data['voices'] ?? [];

        } catch (\Exception $e) {
            Log::error('Error en ElevenLabsService::getAvailableVoices: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Configura la voz a utilizar
     */
    public function setVoiceId(string $voiceId): void
    {
        $this->voiceId = $voiceId;
    }

    /**
     * Obtiene información de la voz actual
     */
    public function getCurrentVoiceInfo(): ?array
    {
        try {
            if (empty($this->apiKey)) {
                return null;
            }

            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'xi-api-key' => $this->apiKey,
                ])
                ->get("{$this->baseUrl}/voices/{$this->voiceId}");

            if (!$response->successful()) {
                Log::error('Error obteniendo info de voz de ElevenLabs: ' . $response->body());
                return null;
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Error en ElevenLabsService::getCurrentVoiceInfo: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verifica si la API de ElevenLabs está disponible
     */
    public function isAvailable(): bool
    {
        try {
            if (empty($this->apiKey)) {
                return false;
            }

            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'xi-api-key' => $this->apiKey,
                ])
                ->get("{$this->baseUrl}/user");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Error verificando disponibilidad de ElevenLabs: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Configuración optimizada para voces en español
     */
    public function getSpanishVoiceSettings(): array
    {
        return [
            'stability' => 0.6,
            'similarity_boost' => 0.9,
            'style' => 0.2,
            'use_speaker_boost' => true
        ];
    }
}