<?php

namespace App\Providers;

use App\Modules\RegulatoryEngine\Repositories\RegulatoryParameterRepository;
use App\Modules\RegulatoryEngine\Services\OperationalExceptionService;
use App\Modules\RegulatoryEngine\Services\PILACalculationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PILACalculationService::class, function ($app) {
            return new PILACalculationService(
                $app->make(OperationalExceptionService::class),
                $app->make(RegulatoryParameterRepository::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
