<?php

use App\Models\Structure;
use App\Models\User;
use App\Support\StructureCourante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
 * Aides partagées par les tests. Les rôles doivent avoir été créés
 * (RolesEtPermissionsSeeder) avant de les appeler.
 */

/** Crée un compte ayant ce rôle dans ce Jardin. */
/**
 * Crée un compte ayant ce rôle dans ce Jardin.
 * EF-D-08 : comme dans la réalité, un administrateur a sa double authentification
 * activée ; passer $doubleAuthentification = false pour tester le cas contraire.
 */
function compteDans(Structure $structure, string $role, bool $doubleAuthentification = true): User
{
    $fabrique = User::factory();

    if ($doubleAuthentification && in_array($role, ['admin-reseau', 'admin-structure'], true)) {
        $fabrique = $fabrique->avecDoubleAuthentification();
    }

    $user = $fabrique->create();
    setPermissionsTeamId($structure->id);
    $user->assignRole($role);
    setPermissionsTeamId(null);

    return $user;
}

/**
 * Pour les tests Livewire, qui ne passent pas par les middlewares HTTP :
 * on se connecte et on fixe le Jardin courant à la main.
 */
function agirDans(Structure $structure, string $role): User
{
    $user = compteDans($structure, $role);
    test()->actingAs($user);
    app(StructureCourante::class)->definir($structure->id);
    setPermissionsTeamId($structure->id);

    return $user;
}
