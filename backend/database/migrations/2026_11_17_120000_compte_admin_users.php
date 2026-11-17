<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Compte administrateur : connexion par identifiant + mot de passe.
 * Crée le compte par défaut (admin / admin123) s'il n'existe encore aucun utilisateur ;
 * il se modifie ensuite dans Paramètres → Compte administrateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('identifiant', 50)->nullable()->unique()->after('name');
        });

        if (DB::table('users')->count() === 0) {
            DB::table('users')->insert([
                'name' => 'Administrateur',
                'identifiant' => 'admin',
                'email' => 'admin@ecole.local',
                'password' => Hash::make('admin123'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['identifiant']);
            $table->dropColumn('identifiant');
        });
    }
};
