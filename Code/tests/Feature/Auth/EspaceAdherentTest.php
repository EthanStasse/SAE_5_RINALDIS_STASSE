<?php

/*
 * EF-D-14 : page adhérent minimale (coordonnées et changement de mot de passe).
 * EF-D-03 : changer son mot de passe en saisissant l'ancien ; les sessions
 * ouvertes ailleurs sont fermées.
 */

use App\Models\Structure;
use App\Models\User;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolesEtPermissionsSeeder::class);

    $this->jardin = Structure::factory()->create(['nom_commercial' => 'Jardin de Thaon']);
    $this->compte = User::factory()->create([
        'name' => 'Camille Martin',
        'email' => 'camille@cocagne.test',
        'password' => 'MotDePasse-Solide-2026',
    ]);
    setPermissionsTeamId($this->jardin->id);
    $this->compte->assignRole('adherent');
    setPermissionsTeamId(null);
});

/** Envoie le formulaire de changement de mot de passe depuis la page Mon compte. */
function changerMotDePasse(string $actuel, string $nouveau, ?string $confirmation = null)
{
    return test()->from('/mon-compte')->put('/user/password', [
        'current_password' => $actuel,
        'password' => $nouveau,
        'password_confirmation' => $confirmation ?? $nouveau,
    ]);
}

describe('page Mon compte (EF-D-14)', function () {
    it('refuse la page aux visiteurs', function () {
        $this->get('/mon-compte')->assertRedirect('/login');
    });

    it("affiche les coordonnées de l'adhérent et son Jardin", function () {
        $this->actingAs($this->compte)
            ->get('/mon-compte')
            ->assertOk()
            ->assertSee('Mon compte')
            ->assertSee('Camille Martin')
            ->assertSee('camille@cocagne.test')
            ->assertSee('Jardin de Thaon')
            ->assertSee('Changer mon mot de passe');
    });

    it('est accessible depuis le menu', function () {
        $this->actingAs($this->compte)
            ->get('/accueil')
            ->assertSee('Mon compte')
            ->assertSee('/mon-compte');
    });
});

describe('changement de mot de passe (EF-D-03)', function () {
    it("change le mot de passe en saisissant l'ancien", function () {
        $this->actingAs($this->compte);

        changerMotDePasse('MotDePasse-Solide-2026', 'Nouveau-Mot-Jardin-2026')
            ->assertSessionHasNoErrors()
            ->assertRedirect('/mon-compte')
            ->assertSessionHas('status', 'password-updated');

        expect(Hash::check('Nouveau-Mot-Jardin-2026', $this->compte->fresh()->password))->toBeTrue();

        // La personne qui vient de changer son mot de passe reste connectée.
        $this->get('/mon-compte')->assertOk()->assertSee('Votre mot de passe a été modifié.');
    });

    it("refuse si l'ancien mot de passe est faux", function () {
        $this->actingAs($this->compte);

        changerMotDePasse('Pas-Le-Bon-2026', 'Nouveau-Mot-Jardin-2026')
            ->assertSessionHasErrorsIn('updatePassword', ['current_password' => 'Le mot de passe actuel est incorrect.']);

        expect(Hash::check('MotDePasse-Solide-2026', $this->compte->fresh()->password))->toBeTrue();
    });

    it('applique la politique de mot de passe (EF-D-05)', function () {
        $this->actingAs($this->compte);

        changerMotDePasse('MotDePasse-Solide-2026', 'jardin')
            ->assertSessionHasErrorsIn('updatePassword', ['password']);

        expect(Hash::check('MotDePasse-Solide-2026', $this->compte->fresh()->password))->toBeTrue();
    });

    it('exige une confirmation identique', function () {
        $this->actingAs($this->compte);

        changerMotDePasse('MotDePasse-Solide-2026', 'Nouveau-Mot-Jardin-2026', 'Autre-Mot-Jardin-2026')
            ->assertSessionHasErrorsIn('updatePassword', ['password']);
    });

    it('ferme une session ouverte ailleurs quand le mot de passe change', function () {
        // Une session ouverte (par exemple celle d'un voleur de mot de passe).
        $this->actingAs($this->compte)->get('/accueil')->assertOk();

        // Le mot de passe est changé depuis un autre appareil.
        $this->compte->forceFill(['password' => Hash::make('Nouveau-Mot-Jardin-2026')])->save();

        // À sa requête suivante, l'ancienne session est fermée.
        $this->get('/accueil')->assertRedirect('/login');
        $this->assertGuest();
    });
});
