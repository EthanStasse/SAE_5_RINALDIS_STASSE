<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Alertes des serres : EF-C-09 (seuil dépassé sur 2 mesures consécutives)
 * et EF-C-10 (capteur muet depuis 30 minutes).
 *
 * Une alerte est « ouverte » tant que fin_le est vide. Elle est fermée
 * automatiquement quand la situation redevient normale : on garde ainsi
 * l'historique (début, fin, valeur) au lieu d'effacer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->index()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('serre_id');
            $table->unsignedBigInteger('boitier_id')->nullable();  // alerte « capteur muet »
            $table->string('type', 20);                            // seuil | capteur_muet
            $table->string('grandeur', 20)->nullable();            // alerte « seuil »
            $table->decimal('valeur', 8, 1)->nullable();           // dernière valeur hors seuil
            $table->string('message', 255);
            $table->timestampTz('debut_le');
            $table->timestampTz('fin_le')->nullable();
            $table->timestampTz('notifiee_le')->nullable();        // e-mail envoyé (EF-C-11)
            $table->timestampsTz();

            $table->foreign(['serre_id', 'structure_id'])
                ->references(['id', 'structure_id'])->on('serres')->cascadeOnDelete();
            $table->foreign(['boitier_id', 'structure_id'])
                ->references(['id', 'structure_id'])->on('boitiers')->cascadeOnDelete();

            $table->index(['serre_id', 'fin_le']);
        });

        DB::statement(
            "ALTER TABLE alertes ADD CONSTRAINT alertes_type_connu CHECK (type IN ('seuil', 'capteur_muet'))"
        );
        DB::statement(
            "ALTER TABLE alertes ADD CONSTRAINT alertes_cible_coherente CHECK (
                (type = 'seuil' AND grandeur IS NOT NULL AND boitier_id IS NULL)
                OR (type = 'capteur_muet' AND boitier_id IS NOT NULL AND grandeur IS NULL)
            )"
        );
        // Jamais deux alertes OUVERTES pour la même cause : relancer la
        // surveillance ne crée pas de doublon (et deux tâches simultanées non plus).
        DB::statement(
            "CREATE UNIQUE INDEX alertes_une_seule_ouverte ON alertes
                (serre_id, type, COALESCE(grandeur, ''), COALESCE(boitier_id, 0))
                WHERE fin_le IS NULL"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('alertes');
    }
};
