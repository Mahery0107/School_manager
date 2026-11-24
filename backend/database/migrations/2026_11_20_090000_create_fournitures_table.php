<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Liste des fournitures scolaires demandées, par année scolaire et par classe. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fournitures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->string('designation', 150);          // ex : Cahier 100 pages grand format
            $table->string('quantite', 30)->nullable();  // ex : 4, 1 rame, 2 paquets
            $table->string('remarque', 150)->nullable(); // ex : couvert en bleu
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['annee_scolaire_id', 'classe_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fournitures');
    }
};
