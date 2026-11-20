<?php

/*
 * EF-D-08 : double authentification (TOTP) obligatoire pour les administrateurs.
 * Manche 1 : le code demandé après le mot de passe, codes de secours,
 * limitation des essais (force brute sur le code à 6 chiffres).
 */

use App\Models\Structure;
use App\Models\User;
use Database\Seeders\RolesEtPermissionsSeeder;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->seed(RolesEtPermissionsSeeder::class);
    $this->jardin = Structure::factory()->create();

    // Un administrateur qui a déjà activé et confirmé sa double authentification.
    $this->secret = (new Google2FA)->generateSecretKey();
    $this->admin = User::factory()->create([
        'email' => 'admin@cocagne.test',
        'password' => 'MotDePasse-Solide-2026',
    ]);
    $this->admin->forceFill([
        'two_factor_secret' => encrypt($this->secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['secours-0001', 'secours-0002'])),
        'two_factor_confirmed_at' => now(),
    ])->save();
    setPermissionsTeamId($this->jardin->id);
    $this->admin->assignRole('admin-structure');
    setPermissionsTeamId(null);

    $this->messageCodeInvalide = 'Ce code est invalide. Vérifiez-le dans votre application et réessayez.';
});

/** Le code à 6 chiffres que l'application du téléphone afficherait maintenant. */
function codeDuTelephone(string $secret): string
{
    return (new Google2FA)->getCurrentOtp($secret);
}

/** Saisit l'e-mail et le mot de passe de l'administrateur. */
function motDePasseAdmin()
{
    return test()->post('/login', ['email' => 'admin@cocagne.test', 'password' => 'MotDePasse-Solide-2026']);
}

describe('code demandé à la connexion (EF-D-08)', function () {
    it('demande le code après le mot de passe, sans connecter tout de suite', function () {
        motDePasseAdmin()->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        $this->get('/two-factor-challenge')
            ->assertOk()
            ->assertSee('Code de vérification')
            ->assertSee('Utiliser un code de secours');
    });

    it('connecte avec le code affiché par le téléphone', function () {
        motDePasseAdmin();

        $this->post('/two-factor-challenge', ['code' => codeDuTelephone($this->secret)])
            ->assertRedirect('/accueil');
        $this->assertAuthenticatedAs($this->admin);
    });

    it('refuse un code faux', function () {
        motDePasseAdmin();

        // Un code différent du bon, quel qu'il soit.
        $faux = str_pad((string) (((int) codeDuTelephone($this->secret) + 500000) % 1000000), 6, '0', STR_PAD_LEFT);

        $this->post('/two-factor-challenge', ['code' => $faux])
            ->assertSessionHasErrors(['code' => $this->messageCodeInvalide]);
        $this->assertGuest();
    });

    it('ne demande pas de code à un adhérent', function () {
        $adherent = User::factory()->create([
            'email' => 'camille@cocagne.test',
            'password' => 'MotDePasse-Solide-2026',
        ]);
        setPermissionsTeamId($this->jardin->id);
        $adherent->assignRole('adherent');
        setPermissionsTeamId(null);

        $this->post('/login', ['email' => 'camille@cocagne.test', 'password' => 'MotDePasse-Solide-2026'])
            ->assertRedirect('/accueil');
        $this->assertAuthenticatedAs($adherent);
    });
});

describe('codes de secours (EF-D-08)', function () {
    it('accepte un code de secours, une seule fois', function () {
        motDePasseAdmin();
        $this->post('/two-factor-challenge', ['recovery_code' => 'secours-0001'])
            ->assertRedirect('/accueil');
        $this->assertAuthenticatedAs($this->admin);

        $this->post('/logout');

        motDePasseAdmin();
        $this->post('/two-factor-challenge', ['recovery_code' => 'secours-0001'])
            ->assertSessionHasErrors(['recovery_code' => $this->messageCodeInvalide]);
        $this->assertGuest();
    });
});

describe('force brute sur le code (EF-D-08)', function () {
    it('limite les essais de code à 5 par minute', function () {
        motDePasseAdmin();

        foreach (range(1, 5) as $essai) {
            $this->post('/two-factor-challenge', ['code' => '000000'])->assertRedirect();
        }

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertTooManyRequests();
    });
});
