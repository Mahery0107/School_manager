<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Frais annuel (en plus de l'écolage) : droit d'entrée, assurance, cotisation FRAM, t-shirt, examen blanc…
 *
 * - portee : 'eleve' (chaque élève) ou 'famille' (une seule fois par famille : frères et sœurs reconnus
 *   par le téléphone du père ou de la mère) ;
 * - obligatoire : 'tous', 'nouveaux' (élèves en 1re inscription seulement) ou 'aucun' ;
 * - facultatif_autres : les élèves non obligés peuvent quand même le payer à la demande (ex. t-shirt) ;
 * - classes : classes concernées (aucune = toutes les classes).
 */
class Frais extends Model
{
    protected $table = 'frais';

    protected $fillable = ['libelle', 'montant', 'portee', 'obligatoire', 'facultatif_autres', 'actif', 'ordre'];

    protected $casts = [
        'montant' => 'integer',
        'facultatif_autres' => 'boolean',
        'actif' => 'boolean',
        'ordre' => 'integer',
    ];

    public const PORTEES = ['eleve' => 'Par élève', 'famille' => 'Par famille'];

    public const OBLIGATOIRE = ['tous' => 'Tous les élèves', 'nouveaux' => 'Nouveaux élèves', 'aucun' => 'Personne (sur demande)'];

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'classe_frais');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    /** Le frais concerne-t-il cette classe ? (aucune classe cochée = toutes) */
    public function concerne(int $classeId): bool
    {
        $this->loadMissing('classes');

        return $this->classes->isEmpty() || $this->classes->contains('id', $classeId);
    }

    /** Frais obligatoire pour une inscription (1re inscription = « nouveau »). */
    public function obligatoirePour(Inscription $inscription): bool
    {
        return $this->obligatoire === 'tous'
            || ($this->obligatoire === 'nouveaux' && $inscription->type === 'inscription');
    }
}
