<?php

namespace App\Http\Controllers\Api;

use App\Console\Commands\SauvegardeBase;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/** Paramètres → Sauvegardes : liste, sauvegarde immédiate, téléchargement (copie hors du serveur). */
class SauvegardeController extends Controller
{
    public function index()
    {
        File::ensureDirectoryExists(SauvegardeBase::dossier());

        return collect(File::files(SauvegardeBase::dossier()))
            ->filter(fn ($f) => preg_match('/\.sql(\.gz)?$/', $f->getFilename()))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->map(fn ($f) => [
                'fichier' => $f->getFilename(),
                'taille' => $f->getSize(),
                'date' => date('Y-m-d H:i', $f->getMTime()),
                'automatique' => str_starts_with($f->getFilename(), SauvegardeBase::PREFIXE),
            ])
            ->values()
            ->pipe(fn ($liste) => [
                'sauvegardes' => $liste,
                'conserver_jours' => (int) config('app.sauvegarde.conserver_jours'),
            ]);
    }

    public function store()
    {
        $code = Artisan::call('ecole:sauvegarde');
        $sortie = trim(Artisan::output());

        if ($code !== 0) {
            return response()->json(['message' => $sortie ?: 'La sauvegarde a échoué.'], 500);
        }

        return ['message' => $sortie];
    }

    public function telecharger(string $fichier)
    {
        // nom de fichier simple uniquement (aucun chemin) et extension .sql / .sql.gz
        abort_unless(preg_match('/^[A-Za-z0-9._-]+\.sql(\.gz)?$/', $fichier), 404);
        $chemin = SauvegardeBase::dossier().DIRECTORY_SEPARATOR.$fichier;
        abort_unless(is_file($chemin), 404);

        return response()->download($chemin, $fichier);
    }
}
