<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles prédéfinis et matrice des droits (cahier des charges, section 2.3).
 * Les rôles sont globaux ; ils sont attribués à un compte DANS une structure.
 * Idempotent : peut être relancé.
 */
class RolesEtPermissionsSeeder extends Seeder
{
    public const MATRICE = [
        'admin-reseau' => [
            'gerer-structures', 'parametrer-structure', 'gerer-comptes', 'gerer-paniers',
            'voir-paniers', 'voir-serres', 'gerer-seuils', 'voir-audit',
        ],
        'admin-structure' => [
            'parametrer-structure', 'gerer-comptes', 'gerer-paniers',
            'voir-paniers', 'voir-serres', 'gerer-seuils', 'voir-audit',
        ],
        'equipe' => ['gerer-paniers', 'voir-paniers', 'voir-serres', 'gerer-seuils'],
        'insertion' => ['voir-paniers', 'voir-serres'],
        'benevole' => ['voir-serres'],
        'adherent' => ['voir-mon-panier'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        setPermissionsTeamId(null);

        foreach (collect(self::MATRICE)->flatten()->unique() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::MATRICE as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
