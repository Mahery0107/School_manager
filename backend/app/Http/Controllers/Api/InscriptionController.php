<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Etudiant;
use App\Models\Inscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InscriptionController extends Controller
{
    public static function reglesEtudiant(): array
    {
        return [
            'nom' => 'required|string|max:100',
            'prenom' => 'nullable|string|max:150',
            'sexe' => 'required|in:M,F',
            'date_naissance' => 'required|date|before:today',
            'lieu_naissance' => 'nullable|string|max:100',
            'adresse' => 'nullable|string|max:255',
            // Au moins un parent (père ou mère) avec un numéro de téléphone joignable
            'nom_pere' => 'nullable|required_without:nom_mere|string|max:150',
            'profession_pere' => 'nullable|string|max:100',
            'telephone_pere' => 'nullable|required_with:nom_pere|required_without:telephone_mere|string|max:30',
            'nom_mere' => 'nullable|required_without:nom_pere|string|max:150',
            'profession_mere' => 'nullable|string|max:100',
            'telephone_mere' => 'nullable|required_with:nom_mere|required_without:telephone_pere|string|max:30',
        ];
    }

    public static function messagesEtudiant(): array
    {
        return [
            'nom_pere.required_without' => 'Renseignez au moins le père ou la mère.',
            'nom_mere.required_without' => 'Renseignez au moins le père ou la mère.',
            'telephone_pere.required_with' => 'Le téléphone du père est obligatoire.',
            'telephone_mere.required_with' => 'Le téléphone de la mère est obligatoire.',
            'telephone_pere.required_without' => 'Au moins un numéro de téléphone (père ou mère) est obligatoire.',
            'telephone_mere.required_without' => 'Au moins un numéro de téléphone (père ou mère) est obligatoire.',
        ];
    }

    /**
     * Liste des inscrits, filtrable par année (année active par défaut), classe et recherche.
     * Chaque ligne indique le statut de l'écolage du mois choisi (?mois=YYYY-MM, mois en cours par défaut) :
     * « paye », « a_payer » (échéance du 10 pas encore passée) ou « en_retard ».
     * ?statut= permet de filtrer sur ce statut.
     */
    public function index(Request $request)
    {
        $request->validate([
            'mois' => 'nullable|date_format:Y-m',
            'statut' => 'nullable|in:paye,a_payer,en_retard,non_paye',
        ]);

        $annee = AnneeScolaire::find($request->query('annee_scolaire_id')) ?: AnneeScolaire::active();
        $q = trim((string) $request->query('q', ''));
        $mois = $this->moisDeReference($annee, $request->query('mois'));
        $echeance = $mois ? Inscription::echeance($mois) : null;
        $echeancePassee = $echeance && now()->toDateString() > $echeance;
        $statut = $request->query('statut');

        // Mois soldé = ses versements atteignent le montant dû (paiement possible en plusieurs fois)
        $soldeSql = "exists (select 1 from paiements p where p.inscription_id = inscriptions.id
            and p.type = 'ecolage' and p.mois = ? group by p.inscription_id having sum(p.montant) >= max(p.montant_du))";
        $solde = fn ($query) => $query->whereRaw($soldeSql, [$mois]);
        $nonSolde = fn ($query) => $query->whereRaw("not $soldeSql", [$mois]);

        $page = Inscription::query()
            ->with(['etudiant', 'classe', 'anneeScolaire', 'paiements'])
            ->join('etudiants', 'etudiants.id', '=', 'inscriptions.etudiant_id')
            ->where('inscriptions.annee_scolaire_id', $annee?->id)
            ->when($request->query('classe_id'), fn ($query, $c) => $query->where('inscriptions.classe_id', $c))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('etudiants.nom', 'like', "%{$q}%")
                ->orWhere('etudiants.prenom', 'like', "%{$q}%")
                ->orWhere('etudiants.matricule', 'like', "%{$q}%")))
            ->when($mois && $statut === 'paye', $solde)
            ->when($mois && $statut === 'non_paye', $nonSolde)
            ->when($mois && $statut === 'a_payer', fn ($query) => $echeancePassee
                ? $query->whereRaw('1 = 0')
                : $nonSolde($query))
            ->when($mois && $statut === 'en_retard', fn ($query) => $echeancePassee
                ? $nonSolde($query)
                : $query->whereRaw('1 = 0'))
            ->orderBy('etudiants.nom')->orderBy('etudiants.prenom')
            ->select('inscriptions.*')
            ->paginate((int) $request->query('per_page', 20));

        $page->getCollection()->transform(function (Inscription $i) use ($mois, $echeance, $echeancePassee) {
            $s = $i->situation();
            $etat = $mois ? $i->etatElement('ecolage', $mois) : null;
            $i->setAttribute('ecolage_mois', $mois ? [
                'mois' => $mois,
                'echeance' => $echeance,
                'montant' => $etat['montant_du'],
                'montant_paye' => $etat['montant_paye'],
                'reste' => $etat['reste'],
                'partiel' => $etat['partiel'],
                'statut' => $etat['solde'] ? 'paye' : ($echeancePassee ? 'en_retard' : 'a_payer'),
            ] : null);
            $i->setAttribute('mois_en_retard', $s['mois_en_retard']);
            $i->setAttribute('droit_paye', $s['droit_paye']);

            return $i->makeHidden('paiements');
        });

        return response()->json($page->toArray() + ['mois' => $mois]);
    }

    /** Mois affiché : celui demandé s'il fait partie de l'année, sinon le mois en cours ramené dans l'année scolaire. */
    private function moisDeReference(?AnneeScolaire $annee, ?string $demande): ?string
    {
        $moisAnnee = $annee?->mois ?? [];
        if (! $moisAnnee) {
            return null;
        }
        if ($demande && in_array($demande, $moisAnnee, true)) {
            return $demande;
        }
        $courant = now()->format('Y-m');

        return min(max($courant, $moisAnnee[0]), end($moisAnnee));
    }

    /** Inscription d'un NOUVEL étudiant : crée l'étudiant + son inscription. */
    public function store(Request $request)
    {
        $data = $request->validate(self::reglesEtudiant() + [
            'classe_id' => 'required|exists:classes,id',
            'annee_scolaire_id' => 'required|exists:annees_scolaires,id',
        ], self::messagesEtudiant());

        $inscription = DB::transaction(function () use ($data) {
            $etudiant = Etudiant::create(
                collect($data)->only(array_keys(self::reglesEtudiant()))->all()
                + ['matricule' => Etudiant::genererMatricule()]
            );

            return $etudiant->inscriptions()->create([
                'classe_id' => $data['classe_id'],
                'annee_scolaire_id' => $data['annee_scolaire_id'],
                'type' => 'inscription',
                'date_inscription' => now()->toDateString(),
            ]);
        });

        return response()->json($this->detail($inscription), 201);
    }

    /** Réinscription d'un ANCIEN élève pour une nouvelle année scolaire. */
    public function reinscrire(Request $request)
    {
        $data = $request->validate([
            'etudiant_id' => 'required|exists:etudiants,id',
            'classe_id' => 'required|exists:classes,id',
            'annee_scolaire_id' => 'required|exists:annees_scolaires,id',
        ]);

        $etudiant = Etudiant::findOrFail($data['etudiant_id']);

        if ($etudiant->inscriptions()->where('annee_scolaire_id', $data['annee_scolaire_id'])->exists()) {
            throw ValidationException::withMessages([
                'etudiant_id' => 'Cet élève est déjà inscrit pour cette année scolaire.',
            ]);
        }

        if (! $etudiant->inscriptions()->exists()) {
            throw ValidationException::withMessages([
                'etudiant_id' => "Cet élève n'a aucune inscription antérieure : utilisez l'inscription d'un nouvel élève.",
            ]);
        }

        $inscription = $etudiant->inscriptions()->create([
            'classe_id' => $data['classe_id'],
            'annee_scolaire_id' => $data['annee_scolaire_id'],
            'type' => 'reinscription',
            'date_inscription' => now()->toDateString(),
        ]);

        return response()->json($this->detail($inscription), 201);
    }

    public function show(Inscription $inscription)
    {
        return $this->detail($inscription);
    }

    public function destroy(Inscription $inscription)
    {
        if ($inscription->paiements()->exists()) {
            return response()->json(['message' => "Impossible d'annuler une inscription ayant déjà des paiements."], 422);
        }
        $inscription->delete();

        return response()->noContent();
    }

    private function detail(Inscription $inscription): array
    {
        $inscription->load(['etudiant', 'classe', 'anneeScolaire', 'paiements' => fn ($q) => $q->orderByDesc('id')]);

        return $inscription->toArray() + ['situation' => $inscription->situation()];
    }
}
