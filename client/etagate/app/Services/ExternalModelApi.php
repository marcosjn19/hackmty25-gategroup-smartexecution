<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

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

    /** Register a model and return its UUID. */
    public function register(string $name, ?string $description = null): string
    {
        $payload = ['name' => $name];
        if (!empty($description)) {
            $payload['description'] = $description;
        }

        $response = Http::asJson()->post($this->url('/register'), $payload);
        $response->throw();

        $data = $response->json();
        $uuid = $data['uuid'] ?? null;

        if (!$uuid || !is_string($uuid)) {
            throw new \RuntimeException("Missing 'uuid' in API response.");
        }

        return $uuid;
    }

    /** GET /available (lista de modelos). */
    public function available(): array
    {
        $response = Http::get($this->url('/available'));
        $response->throw();

        $json = $response->json();
        $items = is_array($json) && array_is_list($json) ? $json : ($json['items'] ?? []);

        $normalized = [];
        foreach ($items as $row) {
            $uuid = $row['uuid'] ?? ($row['id'] ?? null);
            if (!$uuid)
                continue;

            $normalized[] = [
                'uuid' => (string) $uuid,
                'name' => (string) ($row['name'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                // el endpoint no trae “status”; para el listado basta con estos campos
            ];
        }

        return $normalized;
    }

    /** Mapa uuid => row */
    public function availableMap(): array
    {
        $map = [];
        foreach ($this->available() as $row) {
            $map[$row['uuid']] = $row;
        }
        return $map;
    }

    /** POST /upload-sample (una imagen). */
    public function uploadSample(string $uuid, string $type, UploadedFile $file): array
    {
        $response = Http::asMultipart()
            ->attach('image', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post($this->url('/upload-sample'), [
                'uuid' => $uuid,
                'type' => $type,
            ]);

        $response->throw();
        return (array) $response->json();
    }

    /** Helper: varias imágenes. */
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

    /** POST /counts → {positive, negative} */
    public function counts(string $uuid): array
    {
        // Enviar JSON
        $response = Http::asJson()->post($this->url('/counts'), ['uuid' => $uuid]);
        $response->throw();

        $json = (array) $response->json();
        return [
            'positive' => (int) ($json['positive'] ?? null),
            'negative' => (int) ($json['negative'] ?? null),
        ];
    }

    public function train(string $uuid): array
    {
        // Enviar JSON
        $response = Http::asJson()->post($this->url('/train'), ['uuid' => $uuid]);
        $response->throw();

        return (array) $response->json();
    }
}
