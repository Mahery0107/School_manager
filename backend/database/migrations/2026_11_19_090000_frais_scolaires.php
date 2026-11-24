<?php

use App\Models\Classe;
use Database\Seeders\ClasseSeeder;
use Database\Seeders\FraisSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nouveaux frais scolaires : le « droit d'inscription » unique (220 000 Ar) est remplacé par une liste de frais
 * paramétrables (droit d'entrée, assurance, cotisation FRAM par famille, t-shirt, examen blanc).
 *
 * - Les paiements déjà faits sur l'ancien droit restent ('droit') et couvrent les nouveaux frais
 *   de l'inscription concernée (décision de l'établissement).
 * - Nouveaux tarifs d'écolage : MPS→CM2 25 000, 6ème-5ème 28 000, 4ème-3ème 30 000 Ar / mois
 *   (les mois déjà entamés gardent leur montant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frais', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 100)->unique();
            $table->unsignedInteger('montant');
            $table->string('portee', 10)->default('eleve');        // eleve | famille
            $table->string('obligatoire', 10)->default('tous');    // tous | nouveaux | aucun
            $table->boolean('facultatif_autres')->default(false);   // payable à la demande par les autres
            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        Schema::create('classe_frais', function (Blueprint $table) {
            $table->foreignId('frais_id')->constrained('frais')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->primary(['frais_id', 'classe_id']);
        });

        Schema::table('paiements', function (Blueprint $table) {
            $table->string('type', 10)->change();                  // droit (ancien) | ecolage | frais
            $table->foreignId('frais_id')->nullable()->after('mois')->constrained('frais')->restrictOnDelete();
        });

        // l'ancien droit unique n'est plus utilisé pour les nouvelles inscriptions
        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedInteger('droit_inscription')->default(0)->change();
        });

        // Base existante (classes déjà créées) : nouveaux tarifs d'écolage + grille des frais.
        // Base vide (tests) : c'est le DatabaseSeeder qui s'en charge, après la création des classes.
        if (Classe::count() > 0) {
            foreach (ClasseSeeder::CLASSES as $nom => $ecolage) {
                Classe::where('nom', $nom)->update(['ecolage_mensuel' => $ecolage]);
            }
            (new FraisSeeder)->run();
        }
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('frais_id');
        });
        Schema::dropIfExists('classe_frais');
        Schema::dropIfExists('frais');
    }
};
