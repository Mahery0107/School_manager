<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sortie d'un élève en cours d'année (renvoi, abandon, transfert…).
 * L'inscription et ses paiements sont conservés ; l'élève n'apparaît plus parmi les présents
 * et les écolages des mois après son départ ne sont plus dus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->date('date_sortie')->nullable()->after('date_inscription');
            $table->string('motif_sortie', 20)->nullable()->after('date_sortie');   // renvoye | abandon | transfert | autre
            $table->string('remarque_sortie')->nullable()->after('motif_sortie');
        });
    }

    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropColumn(['date_sortie', 'motif_sortie', 'remarque_sortie']);
        });
    }
};
