<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modèle à suivre pour toute table métier : structure_id + clé étrangère + index.
        Schema::create('serres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->index()->constrained()->restrictOnDelete();
            $table->string('nom');
            $table->string('emplacement')->nullable();
            $table->timestamps();

            $table->unique(['structure_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serres');
    }
};
