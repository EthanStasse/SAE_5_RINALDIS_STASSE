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
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // En production, l'application est derrière Caddy (HTTPS) puis nginx.
        // On fait confiance aux en-têtes X-Forwarded-* envoyés par ces deux
        // relais, qui ont des adresses du réseau privé de Docker : Laravel sait
        // alors que la requête d'origine était en HTTPS (liens en https://, en-tête
        // HSTS de EntetesSecurite) et connaît la vraie adresse IP du visiteur
        // (limitation des tentatives de connexion, journal des connexions).
        // Caddy remplace les X-Forwarded-* envoyés par le visiteur : impossible
        // de se faire passer pour une autre adresse IP.
        $middleware->trustProxies(at: ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);

        // ENF-SEC-01 : en-têtes de sécurité sur toutes les réponses.
        $middleware->append(EntetesSecurite::class);

        // Sur chaque page, dans cet ordre :
        // - EF-D-03 : une session ouverte avant un changement de mot de passe est fermée ;
        // - EF-D-10 : expiration des sessions (30 min, 2 h, 12 h au maximum) ;
        // - choix du Jardin courant ;
        // - EF-D-08 : double authentification obligatoire pour les administrateurs.
        $middleware->web(append: [
            AuthenticateSession::class,
            ExpirationSession::class,
            DefinirStructureCourante::class,
            ExigerDoubleAuthentification::class,
        ]);

        // La structure doit être connue avant la liaison des modèles aux routes.
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
