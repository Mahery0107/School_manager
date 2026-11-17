<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Annulation d'un reçu : les lignes restent en base (traçabilité) mais ne comptent plus.
 * - Caissier : nom du compte qui a encaissé / annulé.
 * - Compteur des numéros de reçu par jour, verrouillé pendant l'encaissement :
 *   deux encaissements simultanés ne peuvent jamais obtenir le même numéro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->string('encaisse_par', 100)->nullable()->after('date_paiement');
            $table->timestamp('annule_le')->nullable()->after('encaisse_par');
            $table->string('annule_par', 100)->nullable()->after('annule_le');
            $table->string('annule_motif')->nullable()->after('annule_par');
        });

        Schema::create('recu_sequences', function (Blueprint $table) {
            $table->string('jour', 8)->primary();   // AAAAMMJJ
            $table->unsignedInteger('dernier');      // dernier numéro attribué ce jour-là
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recu_sequences');
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn(['encaisse_par', 'annule_le', 'annule_par', 'annule_motif']);
        });
    }
};
