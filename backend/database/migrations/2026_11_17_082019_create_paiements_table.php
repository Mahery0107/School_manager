<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->string('numero_recu');                 // plusieurs lignes peuvent partager le même reçu
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->enum('type', ['droit', 'ecolage']);
            $table->string('mois', 7)->nullable();         // YYYY-MM pour un écolage, null pour le droit
            $table->unsignedInteger('montant');
            $table->enum('mode', ['especes', 'mobile_money', 'cheque', 'virement']);
            $table->string('reference')->nullable();       // n° transaction / chèque
            $table->date('date_paiement');
            $table->timestamps();

            $table->index('numero_recu');
            // écolage : un mois payé une seule fois (le droit unique est vérifié dans le contrôleur, mois étant NULL)
            $table->unique(['inscription_id', 'type', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
