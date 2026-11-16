<?php

use App\Http\Controllers\SerreController;
use Illuminate\Support\Facades\Route;

// Pas de page publique : un visiteur est envoyé vers la connexion.
Route::redirect('/', '/accueil');

Route::middleware('auth')->group(function () {
    Route::view('/accueil', 'accueil')->name('accueil');
    Route::view('/serres', 'serres.index')->middleware('can:voir-serres')->name('serres.index');
    Route::get('/serres/{serre}', [SerreController::class, 'show'])->name('serres.show');
    Route::get('/serres/{serre}/boitiers', [SerreController::class, 'boitiers'])
        ->middleware('can:parametrer-structure')
        ->name('serres.boitiers');
    Route::get('/serres/{serre}/seuils', [SerreController::class, 'seuils'])
        ->middleware('can:gerer-seuils')
        ->name('serres.seuils');
});
