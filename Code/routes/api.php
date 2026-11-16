<?php

use App\Http\Controllers\Api\V1\BoitierCourantController;
use App\Http\Controllers\Api\V1\MesureController;
use App\Http\Middleware\AuthentifierBoitier;
use Illuminate\Support\Facades\Route;

/*
 * API des boîtiers capteurs (module C). Préfixe automatique : /api.
 * Ni session ni cookie : chaque requête porte le jeton de son boîtier.
 */
Route::prefix('v1')
    ->middleware(AuthentifierBoitier::class)
    ->group(function () {
        Route::get('/boitier', BoitierCourantController::class)->name('api.v1.boitier');
        Route::post('/mesures', [MesureController::class, 'store'])->name('api.v1.mesures.store');
    });
