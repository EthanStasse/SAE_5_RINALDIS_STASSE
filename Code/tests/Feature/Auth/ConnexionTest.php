<?php

/*
 * Connexion par e-mail et mot de passe (module D, Laravel Fortify).
 */

use App\Models\Structure;
use App\Models\User;
use Database\Seeders\RolesEtPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesEtPermissionsSeeder::class);

    $this->jardin = Structure::factory()->create(['nom_commercial' => 'Jardin de Thaon']);
    $this->compte = User::factory()->create([
        'name' => 'Camille Martin',
        'email' => 'camille@cocagne.test',
        'password' => 'MotDePasse-Solide-2026',
    ]);

    setPermissionsTeamId($this->jardin->id);
    $this->compte->assignRole('equipe');
    setPermissionsTeamId(null);
});

describe('page de connexion', function () {
    it('affiche le formulaire en français', function () {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Connexion')
            ->assertSee('Adresse e-mail')
            ->assertSee('Se connecter');
    });

    it('envoie un visiteur vers la connexion', function () {
        $this->get('/')->assertRedirect('/accueil');
        $this->get('/accueil')->assertRedirect('/login');
    });

    it("n'ouvre pas l'inscription libre", function () {
        $this->get('/register')->assertNotFound();
    });
});

describe('connexion', function () {
    it('connecte un compte actif et ouvre son Jardin', function () {
        $this->post('/login', ['email' => 'camille@cocagne.test', 'password' => 'MotDePasse-Solide-2026'])
            ->assertRedirect('/accueil');

        $this->assertAuthenticatedAs($this->compte);

        $this->get('/accueil')
            ->assertOk()
            ->assertSee('Bonjour Camille Martin')
            ->assertSee('Jardin de Thaon');
    });

    it("accepte l'adresse e-mail en majuscules", function () {
        $this->post('/login', ['email' => 'Camille@Cocagne.TEST', 'password' => 'MotDePasse-Solide-2026'])
            ->assertRedirect('/accueil');

        $this->assertAuthenticatedAs($this->compte);
    });

    it('note la date de dernière connexion', function () {
        $this->post('/login', ['email' => 'camille@cocagne.test', 'password' => 'MotDePasse-Solide-2026']);

        expect($this->compte->fresh()?->derniere_connexion_at)->not->toBeNull();
    });

    it('refuse un mauvais mot de passe avec un message générique', function () {
        $this->from('/login')
            ->post('/login', ['email' => 'camille@cocagne.test', 'password' => 'mauvais'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent à aucun compte actif.']);

        $this->assertGuest();
    });

    it('donne le même message pour une adresse inconnue', function () {
        $this->post('/login', ['email' => 'inconnu@cocagne.test', 'password' => 'MotDePasse-Solide-2026'])
            ->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent à aucun compte actif.']);

        $this->assertGuest();
    });

    it('refuse un compte désactivé', function () {
        $this->compte->update(['actif' => false]);

        $this->post('/login', ['email' => 'camille@cocagne.test', 'password' => 'MotDePasse-Solide-2026'])
            ->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent à aucun compte actif.']);

        $this->assertGuest();
    });

    it('affiche les champs manquants en français', function () {
        $this->post('/login', [])
            ->assertSessionHasErrors([
                'email' => 'Le champ adresse e-mail est obligatoire.',
                'password' => 'Le champ mot de passe est obligatoire.',
            ]);
    });
});

describe('déconnexion', function () {
    it('déconnecte le compte', function () {
        $this->actingAs($this->compte)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    });
});
