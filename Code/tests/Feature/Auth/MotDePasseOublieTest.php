<?php

/*
 * EF-D-04 : mot de passe oublié, lien à usage unique valable 60 minutes.
 * EF-D-07 : la réponse ne révèle pas si un compte existe.
 * ENF-SEC-06 : jeton aléatoire, stocké haché, à usage unique, à durée limitée.
 */

use App\Models\Structure;
use App\Models\User;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(RolesEtPermissionsSeeder::class);

    $this->jardin = Structure::factory()->create();
    $this->compte = User::factory()->create([
        'email' => 'camille@cocagne.test',
        'password' => 'MotDePasse-Solide-2026',
    ]);
    setPermissionsTeamId($this->jardin->id);
    $this->compte->assignRole('adherent');
    setPermissionsTeamId(null);

    $this->messageEnvoi = "Si un compte correspond à cette adresse, un lien de réinitialisation vient d'y être envoyé. Il est valable 60 minutes.";
});

describe('demande du lien (EF-D-04)', function () {
    it('propose le lien depuis la page de connexion', function () {
        $this->get('/login')
            ->assertSee('Mot de passe oublié ?')
            ->assertSee('/forgot-password');
    });

    it('affiche le formulaire en français', function () {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Mot de passe oublié')
            ->assertSee('Adresse e-mail')
            ->assertSee('Recevoir le lien');
    });

    it('envoie un lien au compte', function () {
        $this->post('/forgot-password', ['email' => 'camille@cocagne.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', $this->messageEnvoi);

        Notification::assertSentTo($this->compte, ResetPassword::class);
    });
});

describe('messages qui ne révèlent pas les comptes (EF-D-07)', function () {
    it('donne le même message pour une adresse inconnue, sans rien envoyer', function () {
        $this->post('/forgot-password', ['email' => 'inconnu@cocagne.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', $this->messageEnvoi);

        Notification::assertNothingSent();
    });

    it('donne le même message quand on redemande trop vite, sans renvoyer de lien', function () {
        $this->post('/forgot-password', ['email' => 'camille@cocagne.test']);

        $this->post('/forgot-password', ['email' => 'camille@cocagne.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', $this->messageEnvoi);

        Notification::assertSentToTimes($this->compte, ResetPassword::class, 1);
    });
});

describe('jeton (ENF-SEC-06)', function () {
    it('stocke le jeton haché, jamais en clair', function () {
        $this->post('/forgot-password', ['email' => 'camille@cocagne.test']);

        $jeton = null;
        Notification::assertSentTo($this->compte, ResetPassword::class, function (ResetPassword $notification) use (&$jeton) {
            $jeton = $notification->token;

            return true;
        });

        $enBase = DB::table('password_reset_tokens')->where('email', 'camille@cocagne.test')->value('token');

        expect($enBase)->not->toBe($jeton)
            ->and(Hash::check($jeton, $enBase))->toBeTrue()
            ->and(strlen($jeton))->toBeGreaterThanOrEqual(40);
    });
});
