<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * EF-D-11 : journal des connexions réussies, échouées et des blocages.
 *
 * ENF-RGPD, données minimales : ni l'adresse e-mail saisie (faute de frappe ou
 * adresse d'un tiers testée par un attaquant), ni le mot de passe saisi, ni le
 * navigateur. Conservation : 12 mois (purge automatique, JournalConnexion::prunable).
 *
 * Pas de colonne updated_at : une ligne de journal ne se modifie pas.
 * Pas de structure_id : un compte peut appartenir à plusieurs Jardins, et un échec
 * sur une adresse inconnue n'appartient à aucun. Le filtrage par Jardin se fait
 * à la consultation.
 * Compte supprimé : la ligne reste, mais devient anonyme (user_id vidé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_connexions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();  // null : adresse inconnue
            $table->string('evenement', 30);                                          // connexion_reussie | connexion_echouee | compte_bloque
            $table->string('ip', 45)->nullable();                                     // IPv4 ou IPv6
            $table->timestampTz('created_at')->useCurrent()->index();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_connexions');
    }
};
