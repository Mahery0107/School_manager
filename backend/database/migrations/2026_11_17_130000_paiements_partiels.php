<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paiements en plusieurs fois : un même élément (droit d'inscription ou mois d'écolage)
 * peut recevoir plusieurs versements jusqu'à atteindre le montant dû.
 *
 * - montant_du : montant total de l'élément, figé au premier versement
 *   (un changement de tarif ultérieur ne touche pas un élément déjà entamé) ;
 * - suppression de l'unicité (inscription, type, mois) qui interdisait un 2e versement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->unsignedInteger('montant_du')->default(0)->after('montant');
            // index simple, nécessaire à la clé étrangère inscription_id une fois l'unicité retirée (MySQL)
            $table->index(['inscription_id', 'type', 'mois'], 'paiements_element_index');
        });

        // Les paiements existants étaient tous des paiements complets : l'élément est soldé à ce montant.
        DB::table('paiements')->update(['montant_du' => DB::raw('montant')]);

        Schema::table('paiements', function (Blueprint $table) {
            $table->dropUnique(['inscription_id', 'type', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->unique(['inscription_id', 'type', 'mois']);
        });
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropIndex('paiements_element_index');
            $table->dropColumn('montant_du');
        });
    }
};
