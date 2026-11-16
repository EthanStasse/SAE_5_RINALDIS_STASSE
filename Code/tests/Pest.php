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
function compteDans(Structure $structure, string $role): User
{
    $user = User::factory()->create();
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
