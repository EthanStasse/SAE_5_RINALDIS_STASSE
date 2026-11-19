<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * ENF-SEC-01 : en-têtes de sécurité HTTP, posés sur toutes les réponses
 * (pages, API, pages d'erreur). Ils sont testés par EntetesHttpTest.
 */
class EntetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // XSS : le navigateur n'exécute que les scripts du site lui-même.
        $response->headers->set('Content-Security-Policy', $this->politiqueDeContenu());
        // Clickjacking : la page ne peut pas être affichée dans une iframe
        // (doublon de frame-ancestors pour les anciens navigateurs).
        $response->headers->set('X-Frame-Options', 'DENY');
        // Le navigateur ne devine pas le type d'un fichier.
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // L'adresse complète des pages ne part pas vers les sites externes.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Fonctions du navigateur dont le logiciel n'a pas besoin.
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS seulement en HTTPS : en HTTP, le navigateur l'ignore.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function politiqueDeContenu(): string
    {
        [$vite, $viteWs] = $this->serveurViteDeDeveloppement();

        $directives = [
            "default-src 'self'",
            // 'unsafe-eval' : Alpine.js (fourni avec Livewire) évalue ses expressions.
            // Pas de 'unsafe-inline' : un <script> injecté reste bloqué.
            "script-src 'self' 'unsafe-eval' {$vite}",
            // 'unsafe-inline' : styles en ligne de Livewire (wire:loading).
            "style-src 'self' 'unsafe-inline' {$vite}",
            "img-src 'self' data:",
            "font-src 'self' data: {$vite}",
            "connect-src 'self' {$vite} {$viteWs}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];

        return implode('; ', array_map(
            fn (string $directive): string => trim((string) preg_replace('/\s+/', ' ', $directive)),
            $directives,
        ));
    }

    /**
     * Adresse du serveur Vite, seulement pendant « npm run dev » (fichier public/hot).
     * En production, on renvoie des chaînes vides : seul 'self' est autorisé.
     *
     * @return array{0: string, 1: string}
     */
    private function serveurViteDeDeveloppement(): array
    {
        if (! Vite::isRunningHot()) {
            return ['', ''];
        }

        $http = trim((string) file_get_contents(Vite::hotFile()));

        return [$http, (string) preg_replace('#^http#', 'ws', $http)];
    }
}
