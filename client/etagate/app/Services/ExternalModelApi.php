<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalModelApi
{
    private string $baseUrl;

    public function __construct(string $baseUrl = '')
    {
        $this->baseUrl = rtrim($baseUrl ?: (string) config('services.flask.base_url', ''), '/');
    }

    protected function url(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Return the configured base URL used by this service.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /** Register a model and return its UUID. */
    public function register(string $name, ?string $description = null): string
    {
        $payload = ['name' => $name];
        if (!empty($description)) $payload['description'] = $description;

        $response = Http::asJson()->post($this->url('/register'), $payload);
        $response->throw();

        $data = $response->json();
        $uuid = $data['uuid'] ?? null;

        if (!$uuid || !is_string($uuid)) {
            throw new \RuntimeException("Missing 'uuid' in API response.");
        }
        return $uuid;
    }

    /** GET /available */
    public function available(): array
    {
        $response = Http::get($this->url('/available'));
        $response->throw();

        $json = $response->json();
        $items = is_array($json) && array_is_list($json) ? $json : ($json['items'] ?? []);

        $normalized = [];
        foreach ($items as $row) {
            $uuid = $row['uuid'] ?? ($row['id'] ?? null);
            if (!$uuid) continue;

            $normalized[] = [
                'uuid'        => (string) $uuid,
                'name'        => (string) ($row['name'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
            ];
        }
        return $normalized;
    }

    /** Map uuid => row */
    public function availableMap(): array
    {
        $map = [];
        foreach ($this->available() as $row) {
            $map[$row['uuid']] = $row;
        }
        return $map;
    }

    /** POST /upload-sample (UNA imagen). Flask espera el CAMPO 'image'. */
    public function uploadSample(string $uuid, string $type, UploadedFile $file): array
    {
        $stream = fopen($file->getRealPath(), 'r');

        $response = Http::asMultipart()
            ->attach('image', $stream, $file->getClientOriginalName()) // <- CAMPO CORRECTO
            ->post($this->url('/upload-sample'), [
                'uuid' => $uuid,
                'type' => $type,
            ]);

        // Si la API responde con error, lanza para que el controller lo capture y devuelva 422
        $response->throw();

        return (array) $response->json();
    }

    /** Helper: varias imágenes (1x1) */
    public function uploadSamples(string $uuid, string $type, array $files): array
    {
        $results = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $results[] = $this->uploadSample($uuid, $type, $file);
            }
        }
        return $results;
    }

    /** POST /counts → {positive, negative} (JSON) */
    public function counts(string $uuid): array
    {
        $response = Http::asJson()->post($this->url('/counts'), ['uuid' => $uuid]);
        $response->throw();

        $json = (array) $response->json();
        return [
            'positive' => (int) ($json['positive'] ?? 0),
            'negative' => (int) ($json['negative'] ?? 0),
        ];
    }

    /** POST /train (JSON) */
    public function train(string $uuid): array
    {
        $response = Http::asJson()->post($this->url('/train'), ['uuid' => $uuid]);
        $response->throw();
        return (array) $response->json();
    }
}
