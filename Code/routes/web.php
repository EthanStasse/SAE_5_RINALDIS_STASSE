<?php

use App\Http\Controllers\CompteController;
use App\Http\Controllers\SerreController;
use Illuminate\Support\Facades\Route;

// Pas de page publique : un visiteur est envoyé vers la connexion.
Route::redirect('/', '/accueil');

Route::middleware('auth')->group(function () {
    Route::view('/accueil', 'accueil')->name('accueil');

    // EF-D-08 : activation de la double authentification (QR code, codes de secours).
    Route::view('/securite/double-authentification', 'securite.double-authentification')->name('double-authentification');

    // EF-D-14 : page adhérent minimale (coordonnées, changement de mot de passe).
    Route::view('/mon-compte', 'compte.mon-compte')->name('mon-compte');

    // EF-D-02 : création d'un compte par l'équipe (lien d'activation par e-mail).
    Route::get('/comptes/nouveau', [CompteController::class, 'create'])
        ->middleware('can:gerer-comptes')
        ->name('comptes.create');
    Route::post('/comptes', [CompteController::class, 'store'])
        ->middleware('can:gerer-comptes')
        ->name('comptes.store');
    Route::view('/alertes', 'alertes.index')->middleware('can:voir-serres')->name('alertes.index');
    Route::view('/serres', 'serres.index')->middleware('can:voir-serres')->name('serres.index');
    Route::get('/serres/{serre}', [SerreController::class, 'show'])->middleware('can:voir-serres')->name('serres.show');
    Route::get('/serres/{serre}/boitiers', [SerreController::class, 'boitiers'])
        ->middleware('can:parametrer-structure')
        ->name('serres.boitiers');
    Route::get('/serres/{serre}/seuils', [SerreController::class, 'seuils'])
        ->middleware('can:gerer-seuils')
        ->name('serres.seuils');
});
