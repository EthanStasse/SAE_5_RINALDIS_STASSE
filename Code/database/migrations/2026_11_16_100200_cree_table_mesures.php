<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * EF-C-03 / EF-C-04 : mesures de température, humidité et luminosité, datées en UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->index()->constrained()->restrictOnDelete();
            // serre_id est recopiée depuis le boîtier au moment de la réception :
            // si on déplace un boîtier, l'historique reste attaché à la bonne serre.
            $table->unsignedBigInteger('serre_id');
            $table->unsignedBigInteger('boitier_id');

            // Identifiant généré par la passerelle : un renvoi après timeout
            // ne crée pas de doublon (contrainte unique plus bas).
            $table->uuid('uuid');

            // timestamptz : PostgreSQL stocke l'instant en UTC, sans ambiguïté.
            $table->timestampTz('mesure_le');
            $table->decimal('temperature', 5, 2)->nullable();  // °C
            $table->decimal('humidite', 5, 2)->nullable();     // %
            $table->decimal('luminosite', 8, 1)->nullable();   // lux (BH1750 : 0 à 65535)
            $table->timestampTz('recu_le')->useCurrent();      // heure du serveur

            $table->foreign(['serre_id', 'structure_id'])
                ->references(['id', 'structure_id'])->on('serres')->restrictOnDelete();
            $table->foreign(['boitier_id', 'structure_id'])
                ->references(['id', 'structure_id'])->on('boitiers')->restrictOnDelete();

            $table->unique(['boitier_id', 'uuid']);
            // Index du tableau de bord : « mesures de la serre X sur les dernières 24 h ».
            $table->index(['serre_id', 'mesure_le']);
        });

        // Une mesure doit contenir au moins une valeur (un capteur peut être en panne, pas les trois).
        DB::statement(
            'ALTER TABLE mesures ADD CONSTRAINT mesures_au_moins_une_valeur '
            .'CHECK (temperature IS NOT NULL OR humidite IS NOT NULL OR luminosite IS NOT NULL)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('mesures');
    }
};
