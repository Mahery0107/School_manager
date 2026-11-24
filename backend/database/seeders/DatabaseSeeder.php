<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\Inscription;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $precedente = AnneeScolaire::create([
            'libelle' => '2025-2026', 'date_debut' => '2025-09-01', 'date_fin' => '2026-06-30', 'active' => false,
        ]);
        $courante = AnneeScolaire::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-06-30', 'active' => true,
        ]);

        $this->call([ClasseSeeder::class, FraisSeeder::class]);
        $classes = Classe::whereIn('nom', ['CM2', '6ème', '5ème'])->orderBy('ordre')->get()->values();

        // Anciens élèves inscrits l'an dernier (à réinscrire cette année)
        $anciens = [
            ['Rakoto', 'Jean', 'M', '2013-04-12'],
            ['Rasoa', 'Hanta', 'F', '2012-09-03'],
            ['Andriamanana', 'Toky', 'M', '2011-01-25'],
            ['Randria', 'Fara', 'F', '2013-07-18'],
        ];
        foreach ($anciens as $k => [$nom, $prenom, $sexe, $naissance]) {
            $etudiant = Etudiant::create([
                'matricule' => sprintf('ETU-2025-%04d', $k + 1),
                'nom' => $nom, 'prenom' => $prenom, 'sexe' => $sexe, 'date_naissance' => $naissance,
                'lieu_naissance' => 'Antananarivo', 'adresse' => 'Lot II A '.(10 + $k),
                'nom_pere' => 'Père '.$nom, 'profession_pere' => 'Commerçant', 'telephone_pere' => '034 00 000 0'.$k,
                'nom_mere' => 'Mère '.$nom, 'profession_mere' => 'Enseignante', 'telephone_mere' => '033 00 000 0'.$k,
            ]);
            Inscription::create([
                'etudiant_id' => $etudiant->id, 'classe_id' => $classes[$k % 3]->id,
                'annee_scolaire_id' => $precedente->id, 'type' => 'inscription', 'date_inscription' => '2025-09-20',
            ]);
        }
    }
}
