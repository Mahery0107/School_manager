<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classe extends Model
{
    protected $table = 'classes';

    protected $fillable = ['nom', 'ordre', 'droit_inscription', 'ecolage_mensuel'];

    protected $casts = [
        'ordre' => 'integer',
        'droit_inscription' => 'integer',
        'ecolage_mensuel' => 'integer',
    ];

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }
}
