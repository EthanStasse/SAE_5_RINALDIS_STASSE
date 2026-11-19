<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * EF-D-06 : blocage d'un compte 15 minutes après 5 échecs de connexion.
 *
 * Le compteur porte sur l'adresse e-mail saisie, pas sur l'adresse IP :
 * changer d'IP ne le remet pas à zéro (attaque distribuée).
 * Il s'applique aussi aux adresses inconnues : le message de blocage
 * ne révèle donc pas si un compte existe (EF-D-07).
 * Les clés de cache contiennent une empreinte de l'adresse, jamais l'adresse
 * en clair (RGPD, minimisation).
 */
class BlocageComptes
{
    public const ECHECS_MAX = 5;

    public const DUREE_MINUTES = 15;

    public function estBloque(string $email): bool
    {
        return Cache::has($this->cleBlocage($email));
    }

    public function minutesRestantes(string $email): int
    {
        $fin = (int) Cache::get($this->cleBlocage($email), 0);

        return max(1, (int) ceil(($fin - now()->getTimestamp()) / 60));
    }

    public function enregistrerEchec(string $email): void
    {
        $echecs = RateLimiter::hit($this->cleEchecs($email), self::DUREE_MINUTES * 60);

        if ($echecs >= self::ECHECS_MAX) {
            // Le blocage dure 15 minutes à partir du 5e échec.
            $fin = now()->addMinutes(self::DUREE_MINUTES);
            Cache::put($this->cleBlocage($email), $fin->getTimestamp(), $fin);
            RateLimiter::clear($this->cleEchecs($email));
        }
    }

    public function reinitialiser(string $email): void
    {
        RateLimiter::clear($this->cleEchecs($email));
    }

    private function cleEchecs(string $email): string
    {
        return 'connexion-echecs:'.$this->empreinte($email);
    }

    private function cleBlocage(string $email): string
    {
        return 'connexion-blocage:'.$this->empreinte($email);
    }

    private function empreinte(string $email): string
    {
        return hash('sha256', Str::lower($email));
    }
}
