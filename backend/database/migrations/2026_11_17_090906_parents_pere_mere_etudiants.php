<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remplace « parent / tuteur » par le Père et la Mère (nom, profession, téléphone)
 * et supprime le téléphone de l'élève.
 * Les données de l'ancien « parent » sont reprises dans les champs du père (à corriger si besoin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etudiants', function (Blueprint $table) {
            $table->string('nom_pere')->nullable();
            $table->string('profession_pere')->nullable();
            $table->string('telephone_pere')->nullable();
            $table->string('nom_mere')->nullable();
            $table->string('profession_mere')->nullable();
            $table->string('telephone_mere')->nullable();
        });

        DB::table('etudiants')->update([
            'nom_pere' => DB::raw('nom_parent'),
            'telephone_pere' => DB::raw('telephone_parent'),
        ]);

        Schema::table('etudiants', function (Blueprint $table) {
            $table->dropColumn(['telephone', 'nom_parent', 'telephone_parent']);
        });
    }

    public function down(): void
    {
        Schema::table('etudiants', function (Blueprint $table) {
            $table->string('telephone')->nullable();
            $table->string('nom_parent')->nullable();
            $table->string('telephone_parent')->nullable();
        });

        DB::table('etudiants')->update([
            'nom_parent' => DB::raw('COALESCE(nom_pere, nom_mere)'),
            'telephone_parent' => DB::raw('COALESCE(telephone_pere, telephone_mere)'),
        ]);

        Schema::table('etudiants', function (Blueprint $table) {
            $table->dropColumn(['nom_pere', 'profession_pere', 'telephone_pere', 'nom_mere', 'profession_mere', 'telephone_mere']);
        });
    }
};
