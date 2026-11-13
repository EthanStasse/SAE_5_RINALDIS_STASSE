<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structures', function (Blueprint $table) {
            $table->id();
            $table->string('nom_commercial');
            $table->string('raison_sociale')->nullable();
            $table->string('logo_chemin')->nullable();
            $table->string('siege_social')->nullable();
            $table->string('adresse_gestion')->nullable();
            $table->string('telephone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('contact_nom')->nullable();
            $table->string('site_web')->nullable();
            $table->string('siret', 14)->nullable();
            $table->boolean('assujettie_tva')->default(true);
            // Chiffrés par l'application (cast "encrypted") : type text obligatoire.
            $table->text('bic')->nullable();
            $table->text('iban')->nullable();
            $table->text('ics')->nullable();
            $table->json('modules_actifs')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structures');
    }
};
