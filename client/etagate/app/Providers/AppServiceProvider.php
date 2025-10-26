<?php

namespace App\Providers;

use App\Services\GeminiAiService;
use App\Services\ElevenLabsService;
use App\Services\VoiceTranscriptionService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar servicios del asistente de voz como singletons
        $this->app->singleton(GeminiAiService::class, function ($app) {
            return new GeminiAiService();
        });

        $this->app->singleton(ElevenLabsService::class, function ($app) {
            return new ElevenLabsService();
        });

        $this->app->singleton(VoiceTranscriptionService::class, function ($app) {
            return new VoiceTranscriptionService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
