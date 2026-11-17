<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Sauvegarde complète de la base MySQL (mysqldump), compressée en .sql.gz dans storage/app/sauvegardes.
 * Lancée chaque jour par le planificateur (routes/console.php) ; les sauvegardes automatiques
 * de plus de N jours sont supprimées (config app.sauvegarde.conserver_jours).
 */
#[Signature('ecole:sauvegarde')]
#[Description('Sauvegarde la base de données (mysqldump compressé) et supprime les anciennes sauvegardes')]
class SauvegardeBase extends Command
{
    public const PREFIXE = 'auto-';

    public static function dossier(): string
    {
        return storage_path('app/sauvegardes');
    }

    public function handle(): int
    {
        $c = config('database.connections.'.config('database.default'));
        if (! in_array($c['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error('La sauvegarde automatique ne prend en charge que MySQL / MariaDB.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(self::dossier());
        $nom = self::PREFIXE.$c['database'].'-'.now()->format('Ymd-His').'.sql.gz';
        $final = self::dossier().DIRECTORY_SEPARATOR.$nom;
        $temp = $final.'.partiel';

        $process = new Process([
            config('app.sauvegarde.mysqldump'),
            '--host='.$c['host'],
            '--port='.$c['port'],
            '--user='.$c['username'],
            '--single-transaction',   // copie cohérente sans bloquer l'application (InnoDB)
            '--no-tablespaces',       // évite d'exiger le privilège PROCESS sur un VPS
            '--default-character-set=utf8mb4',
            $c['database'],
        ], null, $this->environnement((string) $c['password']));
        $process->setTimeout(900);

        $gz = gzopen($temp, 'wb6');
        $erreurs = '';
        $fin = '';
        $process->run(function ($type, $buffer) use ($gz, &$erreurs, &$fin) {
            if ($type === Process::OUT) {
                gzwrite($gz, $buffer);
                $fin = substr($fin.$buffer, -200);
            } else {
                $erreurs .= $buffer;
            }
        });
        gzclose($gz);

        // mysqldump termine toujours par « -- Dump completed » : sinon la copie est incomplète
        if (! $process->isSuccessful() || ! str_contains($fin, 'Dump completed')) {
            @unlink($temp);
            $this->error('Échec de la sauvegarde : '.trim($erreurs ?: 'copie incomplète'));

            return self::FAILURE;
        }

        rename($temp, $final);
        $this->info("Sauvegarde créée : {$nom} (".number_format(filesize($final) / 1024, 0, ',', ' ').' Ko)');

        $this->supprimerAnciennes();

        return self::SUCCESS;
    }

    /**
     * Variables d'environnement de mysqldump : mot de passe hors de la ligne de commande (MYSQL_PWD).
     * Sous Windows, SystemRoot est indispensable aux connexions réseau ; il manque quand la commande
     * est lancée depuis « php artisan serve » (environnement réduit).
     */
    private function environnement(string $motDePasse): array
    {
        $env = ['MYSQL_PWD' => $motDePasse];
        if (PHP_OS_FAMILY === 'Windows') {
            $env['SystemRoot'] = getenv('SystemRoot') ?: 'C:\Windows';
        }

        return $env;
    }

    /** Supprime les sauvegardes automatiques plus anciennes que la durée de conservation (les manuelles sont gardées). */
    private function supprimerAnciennes(): void
    {
        $limite = now()->subDays(max(1, (int) config('app.sauvegarde.conserver_jours')))->getTimestamp();

        foreach (File::glob(self::dossier().DIRECTORY_SEPARATOR.self::PREFIXE.'*.sql.gz') as $fichier) {
            if (filemtime($fichier) < $limite) {
                File::delete($fichier);
                $this->line('Ancienne sauvegarde supprimée : '.basename($fichier));
            }
        }
    }
}
