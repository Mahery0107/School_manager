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
    public const DROIT_INSCRIPTION = 220000;

    public const CLASSES = [
        // nom => écolage mensuel (Ar)
        'MPS' => 23000,
        'MMS' => 23000,
        'MGS' => 23000,
        'CP1' => 23000,
        'CP2' => 23000,
        'CE' => 23000,
        'CM1' => 23000,
        'CM2' => 23000,
        '6ème' => 25000,
        '5ème' => 25000,
        '4ème' => 25000,
        '3ème' => 25000,
    ];

    public function run(): void
    {
        $ordre = 1;
        foreach (self::CLASSES as $nom => $ecolage) {
            Classe::updateOrCreate(['nom' => $nom], [
                'ordre' => $ordre++,
                'droit_inscription' => self::DROIT_INSCRIPTION,
                'ecolage_mensuel' => $ecolage,
            ]);
        }

        // Classes hors grille (ex : lycée) : supprimées seulement si aucun élève n'y a été inscrit.
        Classe::whereNotIn('nom', array_keys(self::CLASSES))
            ->whereDoesntHave('inscriptions')
            ->delete();
    }
}
