<?php

use Illuminate\Support\Facades\Schedule;

// Sauvegarde quotidienne de la base (nécessite le planificateur : cron « php artisan schedule:run » chaque minute)
Schedule::command('ecole:sauvegarde')
    ->dailyAt(config('app.sauvegarde.heure'))
    ->withoutOverlapping()
    ->onOneServer();
