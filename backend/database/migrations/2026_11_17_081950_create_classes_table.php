<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();                    // ex : 6ème A
            $table->unsignedSmallInteger('ordre')->default(0);  // pour trier / passer en classe supérieure
            $table->unsignedInteger('droit_inscription');       // montant unique par an
            $table->unsignedInteger('ecolage_mensuel');         // montant par mois
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
