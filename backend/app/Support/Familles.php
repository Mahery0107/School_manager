<?php

namespace App\Support;

use App\Models\Inscription;

/**
 * Familles d'une année scolaire : les élèves dont le père ou la mère a le même numéro de téléphone
 * sont frères et sœurs (frais « par famille », ex. cotisation FRAM payée une seule fois).
 *
 * Calculé une fois par année scolaire et par requête (service « scoped »).
 */
class Familles
{
    /** @var array<int, array<int, list<array{id:int, sorti:bool}>>> annee => inscription => membres */
    private array $index = [];

    /** Normalise un numéro : chiffres seulement ; trop court = ignoré. */
    public static function normaliser(?string $telephone): ?string
    {
        $chiffres = preg_replace('/\D+/', '', (string) $telephone);

        return strlen($chiffres) >= 7 ? $chiffres : null;
    }

    /**
     * Membres de la famille de l'inscription (elle comprise), même année scolaire, triés par id.
     *
     * @return list<array{id:int, sorti:bool}>
     */
    public function membres(Inscription $inscription): array
    {
        $annee = $inscription->annee_scolaire_id;
        $this->index[$annee] ??= $this->indexer($annee);

        return $this->index[$annee][$inscription->id]
            ?? [['id' => $inscription->id, 'sorti' => $inscription->date_sortie !== null]];
    }

    /**
     * Inscription qui porte le frais « par famille » : le premier enfant (plus petit id) encore présent.
     */
    public function titulaire(Inscription $inscription): int
    {
        $membres = $this->membres($inscription);
        $presents = array_values(array_filter($membres, fn ($m) => ! $m['sorti']));

        return ($presents ?: $membres)[0]['id'];
    }

    public function oublier(): void
    {
        $this->index = [];
    }

    private function indexer(int $annee): array
    {
        $lignes = Inscription::query()
            ->join('etudiants', 'etudiants.id', '=', 'inscriptions.etudiant_id')
            ->where('inscriptions.annee_scolaire_id', $annee)
            ->orderBy('inscriptions.id')
            ->get(['inscriptions.id', 'inscriptions.date_sortie', 'etudiants.telephone_pere', 'etudiants.telephone_mere']);

        // union-find : deux inscriptions partageant un numéro de parent sont dans la même famille
        $parent = [];
        $racine = function (int $id) use (&$parent, &$racine): int {
            return $parent[$id] === $id ? $id : ($parent[$id] = $racine($parent[$id]));
        };
        $parTelephone = [];
        foreach ($lignes as $l) {
            $parent[$l->id] = $l->id;
            foreach ([$l->telephone_pere, $l->telephone_mere] as $tel) {
                if ($n = self::normaliser($tel)) {
                    if (isset($parTelephone[$n])) {
                        $a = $racine($l->id);
                        $b = $racine($parTelephone[$n]);
                        $parent[max($a, $b)] = min($a, $b);
                    } else {
                        $parTelephone[$n] = $l->id;
                    }
                }
            }
        }

        $groupes = [];
        foreach ($lignes as $l) {
            $groupes[$racine($l->id)][] = ['id' => $l->id, 'sorti' => $l->date_sortie !== null];
        }

        $index = [];
        foreach ($groupes as $membres) {
            foreach ($membres as $m) {
                $index[$m['id']] = $membres;
            }
        }

        return $index;
    }
}
