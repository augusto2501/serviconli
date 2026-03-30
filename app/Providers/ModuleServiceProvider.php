<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Bounded contexts (orden estable para carga de rutas y migraciones).
     *
     * @var list<string>
     */
    protected array $modules = [
        'RegulatoryEngine',
        'Affiliates',
        'Employers',
        'Affiliations',
        'PILALiquidation',
        'Billing',
        'CashReconciliation',
        'Disabilities',
        'Advisors',
        'ThirdParties',
        'Documents',
        'Communications',
        'Security',
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ($this->modules as $module) {
            $base = app_path('Modules/'.$module);

            $web = $base.'/routes/web.php';
            if (is_file($web)) {
                Route::middleware('web')->group(function () use ($web) {
                    require $web;
                });
            }

            $api = $base.'/routes/api.php';
            if (is_file($api)) {
                Route::middleware(['api', 'auth:sanctum'])->prefix('api')->group(function () use ($api) {
                    require $api;
                });
            }

            $migrations = $base.'/database/migrations';
            if (is_dir($migrations)) {
                $this->loadMigrationsFrom($migrations);
            }
        }
    }
}
