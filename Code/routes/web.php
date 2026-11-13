<?php

use App\Http\Controllers\SerreController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/serres/{serre}', [SerreController::class, 'show'])->name('serres.show');
});
