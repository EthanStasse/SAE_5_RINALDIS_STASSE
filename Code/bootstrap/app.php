<?php

use App\Http\Middleware\AuthentifierBoitier;
use App\Http\Middleware\DefinirStructureCourante;
use App\Http\Middleware\EntetesSecurite;
use App\Http\Middleware\ExigerDoubleAuthentification;
use App\Http\Middleware\ExpirationSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ENF-SEC-01 : en-têtes de sécurité sur toutes les réponses.
        $middleware->append(EntetesSecurite::class);

        // EF-D-10 : expiration des sessions, vérifiée avant de choisir le Jardin.
        // EF-D-10 : expiration des sessions ; EF-D-08 : 2FA obligatoire pour les administrateurs.
        $middleware->web(append: [ExpirationSession::class, DefinirStructureCourante::class, ExigerDoubleAuthentification::class]);        // La structure doit être connue avant la liaison des modèles aux routes.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: DefinirStructureCourante::class,
        );
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: AuthentifierBoitier::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // L'API répond toujours en JSON, même si le client oublie l'en-tête Accept.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
