<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ModelController;

// Home → Dashboard público
Route::get('/', fn () => redirect()->route('dashboard'));

// Dashboard público
Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

// Models (público)
Route::resource('models', ModelController::class)->only(['index', 'create', 'store', 'destroy']);

Route::post('/models/{model}/samples', [ModelController::class, 'storeSamples'])->name('models.samples.store');
Route::post('/models/{model}/train', [ModelController::class, 'train'])->name('models.train');

// ⛔️ No cargamos las rutas de autenticación
// require __DIR__ . '/auth.php';
