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
describe('activation (EF-D-08)', function () {
    beforeEach(function () {
        // Un administrateur qui n'a pas encore de double authentification.
        $this->nouvelAdmin = compteDans($this->jardin, 'admin-structure', doubleAuthentification: false);
    });

    it('refuse la page aux visiteurs', function () {
        $this->get('/securite/double-authentification')->assertRedirect('/login');
    });

    it("redemande le mot de passe avant d'activer", function () {
        $this->actingAs($this->nouvelAdmin)
            ->post('/user/two-factor-authentication')
            ->assertRedirect('/user/confirm-password');

        expect($this->nouvelAdmin->fresh()->two_factor_secret)->toBeNull();

        $this->get('/user/confirm-password')
            ->assertOk()
            ->assertSee('Confirmez votre mot de passe');
    });

    it('affiche le QR code et la clé, le secret étant stocké chiffré', function () {
        $this->actingAs($this->nouvelAdmin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/user/two-factor-authentication');

        $enBase = $this->nouvelAdmin->fresh()->two_factor_secret;
        $secret = decrypt($enBase);
        expect($enBase)->not->toContain($secret);

        $this->get('/securite/double-authentification')
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('Clé à saisir à la main')
            ->assertSee($secret);
    });

    it("n'active la 2FA qu'après un premier code correct, puis montre les codes de secours", function () {
        $this->actingAs($this->nouvelAdmin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/user/two-factor-authentication');
        $compte = $this->nouvelAdmin->fresh();
        expect($compte->two_factor_confirmed_at)->toBeNull();

        // Un code faux ne confirme rien.
        $this->post('/user/confirmed-two-factor-authentication', ['code' => '000000'])
            ->assertSessionHasErrorsIn('confirmTwoFactorAuthentication', ['code' => $this->messageCodeInvalide]);
        expect($compte->fresh()->two_factor_confirmed_at)->toBeNull();

        // Le bon code confirme l'activation.
        $this->from('/securite/double-authentification')
            ->post('/user/confirmed-two-factor-authentication', ['code' => codeDuTelephone(decrypt($compte->two_factor_secret))])
            ->assertRedirect('/securite/double-authentification');
        expect($compte->fresh()->two_factor_confirmed_at)->not->toBeNull();

        $this->get('/securite/double-authentification')
            ->assertSee('La double authentification est activée')
            ->assertSee('Codes de secours')
            ->assertSee($compte->fresh()->recoveryCodes()[0]);
    });
});
describe('obligation pour les administrateurs (EF-D-08)', function () {
    beforeEach(function () {
        $this->adminSansCode = compteDans($this->jardin, 'admin-structure', doubleAuthentification: false);
    });

    it("renvoie un administrateur sans double authentification vers l'activation, quelle que soit la page", function (string $page) {
        $this->actingAs($this->adminSansCode)
            ->get($page)
            ->assertRedirect('/securite/double-authentification')
            ->assertSessionHas('avertissement');
    })->with(['/accueil', '/serres', '/alertes']);

    it("lui laisse la page d'activation, la confirmation du mot de passe et la déconnexion", function () {
        $this->actingAs($this->adminSansCode);

        $this->get('/securite/double-authentification')
            ->assertOk()
            ->assertSee('Activer la double authentification');
        $this->get('/user/confirm-password')->assertOk();

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    });

    it('applique la même règle aux administrateurs du réseau', function () {
        $this->actingAs(compteDans($this->jardin, 'admin-reseau', doubleAuthentification: false))
            ->get('/accueil')
            ->assertRedirect('/securite/double-authentification');
    });

    it('laisse passer un administrateur qui a activé sa double authentification', function () {
        $this->actingAs($this->admin)->get('/accueil')->assertOk();
    });

    it("n'impose rien au reste du personnel ni aux adhérents", function (string $role) {
        $this->actingAs(compteDans($this->jardin, $role))
            ->get('/accueil')
            ->assertOk();
    })->with(['equipe', 'insertion', 'benevole', 'adherent']);
});
