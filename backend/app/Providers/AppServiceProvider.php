<?php

namespace App\Providers;

use App\Models\Frais;
use App\Support\Familles;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // calculés une fois par requête (listes, tableau de bord)
        $this->app->scoped(Familles::class);
        $this->app->scoped('frais.actifs', fn () => Frais::actifs()->with('classes')->orderBy('ordre')->orderBy('id')->get());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // familles et frais actifs recalculés à chaque requête (aussi quand l'application reste en mémoire)
        Event::listen(RequestHandled::class, fn () => $this->app->forgetScopedInstances());
    }
}
