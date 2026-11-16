<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * EF-C-02 : boîtier capteur rattaché à une serre, avec un jeton propre et révocable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boitiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained('structures')->restrictOnDelete();
            $table->unsignedBigInteger('serre_id');
            $table->string('nom', 100);

            // On ne stocke JAMAIS le jeton en clair, seulement son empreinte SHA-256.
            // Si la base fuit, les jetons ne sont pas utilisables.
            $table->char('jeton_hash', 64)->unique();
            // 8 premiers caractères, pour que l'utilisateur reconnaisse le jeton dans l'interface.
            $table->string('jeton_prefixe', 8);

            $table->timestampTz('revoque_le')->nullable();
            $table->timestampTz('derniere_communication_le')->nullable();  // pour EF-C-10
            $table->timestampsTz();

            // ISOLATION : un boîtier ne peut appartenir qu'à une serre de SA structure.
            // Même un bug dans le code PHP ne pourrait pas violer cette règle.
            $table->foreign(['serre_id', 'structure_id'])
                ->references(['id', 'structure_id'])
                ->on('serres')
                ->restrictOnDelete();

            // Permet aux mesures de référencer (boitier_id, structure_id).
            $table->unique(['id', 'structure_id']);
            $table->unique(['structure_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boitiers');
    }
};
