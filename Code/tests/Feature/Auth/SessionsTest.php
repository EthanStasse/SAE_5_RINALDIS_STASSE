<?php

/*
 * ENF-SEC-04 : cookie de session HttpOnly et SameSite=Lax, identifiant régénéré à la connexion.
 * EF-D-10 : pas de connexion longue durée (« Rester connecté ») ; expiration selon le rôle.
 */

use App\Models\Structure;
use App\Models\User;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;

beforeEach(function () {
    $this->seed(RolesEtPermissionsSeeder::class);

    $this->jardin = Structure::factory()->create();
    $this->compte = User::factory()->create([
        'email' => 'camille@cocagne.test',
        'password' => 'MotDePasse-Solide-2026',
    ]);
    setPermissionsTeamId($this->jardin->id);
    $this->compte->assignRole('adherent');
    setPermissionsTeamId(null);
});

/** Le cookie de la réponse dont le nom commence par $prefixe, ou null. */
function cookieDeReponse(TestResponse $reponse, string $prefixe): ?Cookie
{
    return collect($reponse->headers->getCookies())
        ->first(fn ($cookie) => str_starts_with($cookie->getName(), $prefixe));
}

describe('cookie de session (ENF-SEC-04)', function () {
    it('pose un cookie de session HttpOnly et SameSite=Lax', function () {
        $cookie = cookieDeReponse($this->get('/login'), config('session.cookie'));

        expect($cookie)->not->toBeNull()
            ->and($cookie->isHttpOnly())->toBeTrue()
            ->and($cookie->getSameSite())->toBe('lax');
    });
});

describe('connexion (ENF-SEC-04, EF-D-10)', function () {
    it("change l'identifiant de session à la connexion (fixation de session)", function () {
        $this->get('/login');
        $avant = session()->getId();

        $this->post('/login', ['email' => 'camille@cocagne.test', 'password' => 'MotDePasse-Solide-2026'])
            ->assertRedirect('/accueil');

        expect(session()->getId())->not->toBe($avant);
    });

    it('ne propose pas la case « Rester connecté »', function () {
        $this->get('/login')->assertDontSee('Rester connecté');
    });

    it('ignore « Rester connecté » même si le champ est envoyé : pas de cookie longue durée', function () {
        $reponse = $this->post('/login', [
            'email' => 'camille@cocagne.test',
            'password' => 'MotDePasse-Solide-2026',
            'remember' => 'on',
        ]);

        $reponse->assertRedirect('/accueil');
        expect(cookieDeReponse($reponse, 'remember_web_'))->toBeNull();
    });
});
describe('expiration selon le rôle (EF-D-10)', function () {
    beforeEach(function () {
        $this->freezeTime();
        $this->messageExpiration = 'Votre session a expiré. Reconnectez-vous pour continuer.';
    });

    it('déconnecte le personnel après 30 minutes sans activité', function () {
        $equipe = compteDans($this->jardin, 'equipe');
        $this->actingAs($equipe)->get('/accueil')->assertOk();

        // Une activité à 29 minutes relance le compteur.
        $this->travel(29)->minutes();
        $this->get('/accueil')->assertOk();

        // 31 minutes sans rien faire : déconnecté.
        $this->travel(31)->minutes();
        $this->get('/accueil')
            ->assertRedirect('/login')
            ->assertSessionHas('status', $this->messageExpiration);
        $this->assertGuest();
    });

    it("laisse l'adhérent connecté jusqu'à 2 heures sans activité", function () {
        $this->actingAs($this->compte)->get('/accueil')->assertOk();

        $this->travel(119)->minutes();
        $this->get('/accueil')->assertOk();

        $this->travel(121)->minutes();
        $this->get('/accueil')->assertRedirect('/login');
        $this->assertGuest();
    });

    it('applique la règle du personnel à un adhérent qui est aussi bénévole', function () {
        setPermissionsTeamId($this->jardin->id);
        $this->compte->assignRole('benevole');
        setPermissionsTeamId(null);

        $this->actingAs($this->compte)->get('/accueil')->assertOk();

        $this->travel(31)->minutes();
        $this->get('/accueil')->assertRedirect('/login');
        $this->assertGuest();
    });

    it('déconnecte au bout de 12 heures, même avec une activité continue', function () {
        $this->actingAs($this->compte)->get('/accueil')->assertOk();

        // Une activité chaque heure pendant 11 h 59.
        foreach (range(1, 11) as $heure) {
            $this->travel(60)->minutes();
            $this->get('/accueil')->assertOk();
        }
        $this->travel(59)->minutes();
        $this->get('/accueil')->assertOk();

        // 12 h 01 après la connexion : déconnecté, malgré l'activité.
        $this->travel(2)->minutes();
        $this->get('/accueil')
            ->assertRedirect('/login')
            ->assertSessionHas('status', $this->messageExpiration);
        $this->assertGuest();
    });
});
