<?php

namespace Database\Seeders;

use App\Models\Classe;
use Illuminate\Database\Seeder;

/**
 * Grille des classes et tarifs de l'établissement (de la maternelle à la 3ème, classe finale).
 * Idempotent : peut être relancé sur une base existante (php artisan db:seed --class=ClasseSeeder).
 */
class ClasseSeeder extends Seeder
{
    public const CLASSES = [
        // nom => écolage mensuel (Ar) ; les autres frais annuels sont dans FraisSeeder
        'MPS' => 25000,
        'MMS' => 25000,
        'MGS' => 25000,
        'CP1' => 25000,
        'CP2' => 25000,
        'CE' => 25000,
        'CM1' => 25000,
        'CM2' => 25000,
        '6ème' => 28000,
        '5ème' => 28000,
        '4ème' => 30000,
        '3ème' => 30000,
    ];

    public function run(): void
    {
        $ordre = 1;
        foreach (self::CLASSES as $nom => $ecolage) {
            Classe::updateOrCreate(['nom' => $nom], [
                'ordre' => $ordre++,
                'ecolage_mensuel' => $ecolage,
            ]);
        }

        // Classes hors grille (ex : lycée) : supprimées seulement si aucun élève n'y a été inscrit.
        Classe::whereNotIn('nom', array_keys(self::CLASSES))
            ->whereDoesntHave('inscriptions')
            ->delete();
    }
}
