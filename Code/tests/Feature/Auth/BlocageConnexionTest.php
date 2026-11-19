<?php

/*
 * EF-D-06 : blocage du compte 15 minutes après 5 échecs, et limitation par adresse IP.
 * EF-D-07 : le blocage ne révèle pas si un compte existe.
 * Les adresses 203.0.113.x et 198.51.100.x sont réservées à la documentation (RFC 5737).
 */

use App\Models\Structure;
use App\Models\User;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(RolesEtPermissionsSeeder::class);

    // Un vrai compte a toujours un rôle dans un Jardin : sans cela,
    // DefinirStructureCourante refuse ses requêtes (403), /logout compris.
    $this->jardin = Structure::factory()->create();
    $this->compte = User::factory()->create([
        'email' => 'camille@cocagne.test',
        'password' => 'MotDePasse-Solide-2026',
    ]);
    setPermissionsTeamId($this->jardin->id);
    $this->compte->assignRole('adherent');
    setPermissionsTeamId(null);

    $this->messageBlocage = "Trop d'échecs de connexion. Pour votre sécurité, réessayez dans 15 min.";
});

/** Envoie $fois tentatives avec un mauvais mot de passe. */
function echouerConnexion(string $email, int $fois, string $ip = '127.0.0.1'): void
{
    for ($i = 0; $i < $fois; $i++) {
        test()->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/login', ['email' => $email, 'password' => 'Mauvais-Mot-2026']);
    }
}

/** Tente la connexion de Camille avec le bon mot de passe. */
function seConnecter(string $ip = '127.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->post('/login', ['email' => 'camille@cocagne.test', 'password' => 'MotDePasse-Solide-2026']);
}

describe('blocage du compte (EF-D-06)', function () {
    it('laisse encore se connecter après 4 échecs', function () {
        echouerConnexion('camille@cocagne.test', 4);

        seConnecter()->assertRedirect('/accueil');
        $this->assertAuthenticatedAs($this->compte);
    });

    it('bloque le compte au 5e échec, même avec le bon mot de passe ensuite', function () {
        echouerConnexion('camille@cocagne.test', 5);

        seConnecter()->assertSessionHasErrors(['email' => $this->messageBlocage]);
        $this->assertGuest();
    });

    it('débloque le compte au bout de 15 minutes', function () {
        $this->freezeTime();
        echouerConnexion('camille@cocagne.test', 5);

        $this->travel(14)->minutes();
        seConnecter()->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(1)->minutes();
        seConnecter()->assertRedirect('/accueil');
        $this->assertAuthenticatedAs($this->compte);
    });

    it('remet le compteur à zéro après une connexion réussie', function () {
        echouerConnexion('camille@cocagne.test', 4);
        seConnecter()->assertRedirect('/accueil');
        $this->post('/logout');

        echouerConnexion('camille@cocagne.test', 4, '10.0.0.2');
        seConnecter('10.0.0.2')->assertRedirect('/accueil');
        $this->assertAuthenticatedAs($this->compte);
    });

    it("bloque même quand les essais viennent d'adresses IP différentes", function () {
        foreach (range(1, 5) as $i) {
            echouerConnexion('camille@cocagne.test', 1, "10.0.0.{$i}");
        }

        seConnecter('10.0.0.99')->assertSessionHasErrors(['email' => $this->messageBlocage]);
        $this->assertGuest();
    });

    it('ne bloque pas les autres comptes', function () {
        $autre = User::factory()->create([
            'email' => 'dominique@cocagne.test',
            'password' => 'MotDePasse-Solide-2026',
        ]);
        echouerConnexion('camille@cocagne.test', 5);

        $this->post('/login', ['email' => 'dominique@cocagne.test', 'password' => 'MotDePasse-Solide-2026'])
            ->assertRedirect('/accueil');
        $this->assertAuthenticatedAs($autre);
    });
});

describe('messages qui ne révèlent pas les comptes (EF-D-07)', function () {
    it('bloque aussi une adresse inconnue, avec le même message', function () {
        echouerConnexion('inconnu@cocagne.test', 5);

        $this->post('/login', ['email' => 'inconnu@cocagne.test', 'password' => 'MotDePasse-Solide-2026'])
            ->assertSessionHasErrors(['email' => $this->messageBlocage]);
    });

    it("ne contourne pas le blocage en changeant la casse de l'adresse", function () {
        echouerConnexion('Camille@Cocagne.TEST', 5);

        seConnecter()->assertSessionHasErrors(['email' => $this->messageBlocage]);
        $this->assertGuest();
    });
});

describe('limitation par adresse IP (EF-D-06)', function () {
    it('limite une même adresse IP à 10 tentatives par minute, tous comptes confondus', function () {
        foreach (range(1, 10) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
                ->post('/login', ['email' => "compte{$i}@cocagne.test", 'password' => 'Mauvais-Mot-2026'])
                ->assertRedirect();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->post('/login', ['email' => 'compte11@cocagne.test', 'password' => 'Mauvais-Mot-2026'])
            ->assertTooManyRequests();
    });

    it('ne gêne pas les autres adresses IP', function () {
        foreach (range(1, 11) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
                ->post('/login', ['email' => "compte{$i}@cocagne.test", 'password' => 'Mauvais-Mot-2026']);
        }

        seConnecter('198.51.100.1')->assertRedirect('/accueil');
        $this->assertAuthenticatedAs($this->compte);
    });

    it('affiche une page en français avec le délai à attendre', function () {
        foreach (range(1, 11) as $i) {
            $reponse = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
                ->post('/login', ['email' => "compte{$i}@cocagne.test", 'password' => 'Mauvais-Mot-2026']);
        }

        $reponse->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertSee('Trop de tentatives')
            ->assertSee('Revenir à la connexion');
    });
});
