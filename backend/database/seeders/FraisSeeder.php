<?php

namespace Database\Seeders;

use App\Models\Classe;
use App\Models\Frais;
use Illuminate\Database\Seeder;

/**
 * Grille des frais annuels de l'établissement (en plus de l'écolage mensuel).
 * Idempotent : un frais déjà présent (même libellé) n'est pas modifié — les montants se changent dans Paramètres.
 */
class FraisSeeder extends Seeder
{
    public const FRAIS = [
        // libellé => [montant, portée, obligatoire, facultatif pour les autres, classes (vide = toutes)]
        "Droit d'entrée" => [5000, 'eleve', 'nouveaux', false, ['MPS', 'MMS', 'MGS', 'CP1']],
        'Frais généraux - Assurance' => [60000, 'eleve', 'tous', false, []],
        'Cotisation FRAM & S.E' => [30000, 'famille', 'tous', false, []],
        'T-shirt + cravate' => [15000, 'eleve', 'nouveaux', true, []],
        'Examen blanc' => [3000, 'eleve', 'tous', false, ['CM2', '3ème']],
    ];

    public function run(): void
    {
        $ordre = 1;
        foreach (self::FRAIS as $libelle => [$montant, $portee, $obligatoire, $facultatif, $classes]) {
            $frais = Frais::firstOrCreate(['libelle' => $libelle], [
                'montant' => $montant,
                'portee' => $portee,
                'obligatoire' => $obligatoire,
                'facultatif_autres' => $facultatif,
                'actif' => true,
                'ordre' => $ordre,
            ]);
            $ordre++;

            if ($frais->wasRecentlyCreated && $classes) {
                $frais->classes()->sync(Classe::whereIn('nom', $classes)->pluck('id'));
            }
        }
    }
}
