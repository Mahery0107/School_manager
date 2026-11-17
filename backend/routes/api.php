<?php

use App\Http\Controllers\Api\AnneeScolaireController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClasseController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EtudiantController;
use App\Http\Controllers\Api\InscriptionController;
use App\Http\Controllers\Api\PaiementController;
use Illuminate\Support\Facades\Route;

Route::get('/etablissement', fn () => ['nom' => config('app.ecole')]);
// Connexion (publique, limitée à 5 essais par minute)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Tout le reste exige le compte administrateur connecté
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/moi', [AuthController::class, 'moi']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/compte', [AuthController::class, 'modifierCompte']);

    Route::get('/dashboard', DashboardController::class);

    // Référentiels
    Route::get('/annees-scolaires', [AnneeScolaireController::class, 'index']);
    Route::post('/annees-scolaires', [AnneeScolaireController::class, 'store']);
    Route::post('/annees-scolaires/{annee}/activer', [AnneeScolaireController::class, 'activer']);
    Route::apiResource('classes', ClasseController::class)
        ->except('show')
        ->parameters(['classes' => 'classe']);

    // Étudiants
    Route::get('/etudiants', [EtudiantController::class, 'index']);
    Route::get('/etudiants/{etudiant}', [EtudiantController::class, 'show']);
    Route::put('/etudiants/{etudiant}', [EtudiantController::class, 'update']);

    // Inscription (nouvel élève) / réinscription (ancien élève)
    Route::get('/inscriptions', [InscriptionController::class, 'index']);
    Route::post('/inscriptions', [InscriptionController::class, 'store']);
    Route::post('/reinscriptions', [InscriptionController::class, 'reinscrire']);
    Route::get('/inscriptions/{inscription}', [InscriptionController::class, 'show']);
    Route::delete('/inscriptions/{inscription}', [InscriptionController::class, 'destroy']);

    // Paiements (droit d'inscription + écolages)
    Route::get('/paiements', [PaiementController::class, 'index']);
    Route::post('/paiements', [PaiementController::class, 'store']);
    Route::get('/recus/{numero}', [PaiementController::class, 'recu']);
    Route::get('/recus/{numero}/pdf', [PaiementController::class, 'pdf']);

});
