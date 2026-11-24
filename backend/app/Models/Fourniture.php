<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Fourniture scolaire demandée pour une classe, une année scolaire donnée. */
class Fourniture extends Model
{
    protected $fillable = ['annee_scolaire_id', 'classe_id', 'designation', 'quantite', 'remarque', 'ordre'];

    protected $casts = [
        'annee_scolaire_id' => 'integer',
        'classe_id' => 'integer',
        'ordre' => 'integer',
    ];

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }
}
