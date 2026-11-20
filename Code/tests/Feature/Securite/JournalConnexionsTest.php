<?php

/*
 * EF-D-11 : journal des connexions réussies, échouées et des blocages.
 * ENF-RGPD : données minimales (ni adresse e-mail saisie, ni mot de passe).
 */

use App\Models\JournalConnexion;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Google2FA\Google2FA;

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

/** Nombre de lignes du journal pour cet événement et ce compte (null = adresse inconnue). */
function lignesDuJournal(string $evenement, ?int $userId): int
{
    return JournalConnexion::query()
        ->where('evenement', $evenement)
        ->where('user_id', $userId)
        ->count();
}

describe('enregistrement (EF-D-11)', function () {
    it("note une connexion réussie, avec la date et l'adresse IP", function () {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->post('/login', ['email' => 'camille@cocagne.test', 'password' => 'MotDePasse-Solide-2026'])
            ->assertRedirect('/accueil');

        $ligne = JournalConnexion::query()->sole();
        expect($ligne->evenement)->toBe(JournalConnexion::REUSSIE)
            ->and($ligne->user_id)->toBe($this->compte->id)
            ->and($ligne->ip)->toBe('203.0.113.9')
            ->and($ligne->created_at)->not->toBeNull();
    });

    it('note un échec sur un compte existant', function () {
        $this->post('/login', ['email' => 'camille@cocagne.test', 'password' => 'Mauvais-Mot-2026']);

        expect(lignesDuJournal(JournalConnexion::ECHOUEE, $this->compte->id))->toBe(1);
    });

    it('note un échec sur une adresse inconnue, sans garder cette adresse', function () {
        $this->post('/login', ['email' => 'inconnu@cocagne.test', 'password' => 'Mauvais-Mot-2026']);

        expect(lignesDuJournal(JournalConnexion::ECHOUEE, null))->toBe(1);
        expect(json_encode(JournalConnexion::query()->sole()->toArray()))->not->toContain('inconnu@cocagne.test');
    });

    it('note le blocage du compte au 5e échec', function () {
        foreach (range(1, 5) as $essai) {
            $this->post('/login', ['email' => 'camille@cocagne.test', 'password' => 'Mauvais-Mot-2026']);
        }

        expect(lignesDuJournal(JournalConnexion::ECHOUEE, $this->compte->id))->toBe(5)
            ->and(lignesDuJournal(JournalConnexion::BLOCAGE, $this->compte->id))->toBe(1);
    });

    it("note la connexion d'un administrateur après son code, pas avant", function () {
        $admin = compteDans($this->jardin, 'admin-structure');
        $admin->update(['password' => 'MotDePasse-Solide-2026']);

        $this->post('/login', ['email' => $admin->email, 'password' => 'MotDePasse-Solide-2026'])
            ->assertRedirect('/two-factor-challenge');
        expect(lignesDuJournal(JournalConnexion::REUSSIE, $admin->id))->toBe(0);

        $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP')]);
        expect(lignesDuJournal(JournalConnexion::REUSSIE, $admin->id))->toBe(1);
    });
});

describe('données minimales (ENF-RGPD)', function () {
    it("n'a ni colonne pour l'adresse saisie, ni pour le mot de passe, ni pour le navigateur", function () {
        expect(Schema::getColumnListing('journal_connexions'))
            ->toContain('user_id', 'evenement', 'ip', 'created_at')
            ->not->toContain('email')
            ->not->toContain('password')
            ->not->toContain('user_agent')
            ->not->toContain('updated_at');
    });

    it('ne garde jamais le mot de passe saisi', function () {
        $this->post('/login', ['email' => 'camille@cocagne.test', 'password' => 'Mauvais-Mot-2026']);

        expect(json_encode(JournalConnexion::query()->get()->toArray()))->not->toContain('Mauvais-Mot-2026');
    });
});
