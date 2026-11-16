<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * EF-C-08 : seuils minimum et maximum par serre et par grandeur.
 * Un seuil peut n'avoir qu'une borne (par exemple seulement un maximum de température).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seuils', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->index()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('serre_id');
            $table->string('grandeur', 20);  // temperature | humidite | luminosite (enum App\Enums\Grandeur)
            $table->decimal('minimum', 8, 1)->nullable();
            $table->decimal('maximum', 8, 1)->nullable();
            $table->timestampsTz();

            // Isolation : un seuil appartient forcément à une serre de son Jardin.
            // Supprimer la serre supprime ses seuils (ils n'ont pas de sens sans elle).
            $table->foreign(['serre_id', 'structure_id'])
                ->references(['id', 'structure_id'])->on('serres')
                ->cascadeOnDelete();

            // Un seul seuil par grandeur et par serre.
            $table->unique(['serre_id', 'grandeur']);
        });

        DB::statement(
            "ALTER TABLE seuils ADD CONSTRAINT seuils_grandeur_connue CHECK (grandeur IN ('temperature', 'humidite', 'luminosite'))"
        );
        DB::statement(
            'ALTER TABLE seuils ADD CONSTRAINT seuils_au_moins_une_borne CHECK (minimum IS NOT NULL OR maximum IS NOT NULL)'
        );
        DB::statement(
            'ALTER TABLE seuils ADD CONSTRAINT seuils_minimum_inferieur_maximum CHECK (minimum IS NULL OR maximum IS NULL OR minimum < maximum)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('seuils');
    }
};
