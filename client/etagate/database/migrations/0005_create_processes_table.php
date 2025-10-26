<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('processes', function (Blueprint $table) {
            $table->id();

            // Básico
            $table->string('name');
            $table->string('status')->default('pending'); // pending|running|succeeded|failed|canceled
            $table->json('payload')->nullable();          // configuración libre del proceso

            // Tiempos
            $table->dateTime('run_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->string('error_message', 1000)->nullable();

            // Config. de inferencia global (0..1). Se valida en la app.
            $table->decimal('default_threshold', 5, 4)->nullable();

            /**
             * MODELS (JSON)
             * Lista ordenada de modelos asignados al proceso.
             * Estructura esperada:
             * [
             *   {
             *     "model_uuid": "e.g. 8e1b3f2a-...-c0a7",
             *     "name": "Casco-Detector",
             *     "order_index": 1,
             *     "threshold": 0.70  // opcional; si no, usa default_threshold
             *   },
             *   {
             *     "model_uuid": "...",
             *     "name": "Chaleco-Detector",
             *     "order_index": 2
             *   }
             * ]
             */
            $table->json('models')->nullable();

            // Agregados globales
            $table->unsignedBigInteger('total_images')->default(0); // imágenes recibidas/validadas
            $table->unsignedBigInteger('positives')->default(0);    // suma de aprobados (todas las inferencias)
            $table->unsignedBigInteger('negatives')->default(0);

            // Métricas globales
            $table->unsignedInteger('avg_latency_ms')->nullable();  // promedio simple
            $table->unsignedInteger('p95_latency_ms')->nullable();  // percentil 95
            $table->unsignedInteger('max_latency_ms')->nullable();
            $table->decimal('avg_confidence', 5, 4)->nullable();    // promedio de confidencias

            /**
             * STATS (JSON)
             * Acumuladores para insights por modelo y buffers de muestras.
             * Estructura sugerida:
             * {
             *   "global": {
             *     "latency_samples": [120, 134, 98, ...],   // últimos N (p.ej., 200) para p95/avg
             *     "confidence_sum": 12.3456,                // suma incremental de confidencias
             *     "confidence_count": 18                    // contador para promedio
             *   },
             *   "by_model": {
             *     "<model_uuid>": {
             *       "inferences": 37,
             *       "positives": 12,
             *       "negatives": 25,
             *       "confidence_sum": 9.8765,
             *       "confidence_count": 14,
             *       "latency_samples": [110, 145, 130, ...] // buffer por modelo
             *     }
             *   }
             * }
             */
            $table->json('stats')->nullable();

            /**
             * LAST_REQUEST (JSON)
             * Último lote de resultados (útil para UI/detalle rápido).
             * Estructura:
             * {
             *   "received_at": "2025-10-26T01:23:45Z",
             *   "image_name": "frame_000231.jpg",
             *   "results": [
             *     {
             *       "model_uuid": "8e1b3f2a-...-c0a7",
             *       "approved": true,
             *       "confidence": 0.82,
             *       "threshold": 0.70,
             *       "latency_ms": 115,
             *       "request_id": "a2b3c4d5-..."
             *     },
             *     { "...": "..." }
             *   ]
             * }
             */
            $table->json('last_request')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processes');
    }
};
