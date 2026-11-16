<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Prépare l'isolation entre Jardins au niveau de la base de données.
 *
 * Le couple (id, structure_id) est forcément unique puisque id l'est déjà.
 * Mais PostgreSQL n'accepte une clé étrangère composite que vers une
 * colonne (ou un groupe de colonnes) déclarée UNIQUE. Cette contrainte
 * permet donc aux boîtiers et aux mesures de référencer
 * (serre_id, structure_id) ensemble.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serres', function (Blueprint $table) {
            $table->unique(['id', 'structure_id'], 'serres_id_structure_unique');
        });
    }

    public function down(): void
    {
        Schema::table('serres', function (Blueprint $table) {
            $table->dropUnique('serres_id_structure_unique');
        });
    }
};
