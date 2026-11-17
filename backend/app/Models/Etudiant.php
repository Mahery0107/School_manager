<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Etudiant extends Model
{
    protected $fillable = [
        'matricule', 'nom', 'prenom', 'sexe', 'date_naissance', 'lieu_naissance', 'adresse',
        'nom_pere', 'profession_pere', 'telephone_pere',
        'nom_mere', 'profession_mere', 'telephone_mere',
    ];

    protected $casts = [
        'date_naissance' => 'date:Y-m-d',
    ];

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    public function derniereInscription(): HasOne
    {
        return $this->hasOne(Inscription::class)->latestOfMany('annee_scolaire_id');
    }

    /** Génère un matricule unique du type ETU-2026-0001. */
    public static function genererMatricule(): string
    {
        $prefix = 'ETU-'.now()->format('Y').'-';
        $dernier = static::where('matricule', 'like', $prefix.'%')->orderByDesc('matricule')->value('matricule');
        $numero = $dernier ? ((int) substr($dernier, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }
}
