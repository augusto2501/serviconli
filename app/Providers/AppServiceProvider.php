<?php

namespace App\Providers;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\EnrollmentProcess;
use App\Modules\Affiliates\Models\ReentryProcess;
use App\Modules\Employers\Models\Employer;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use App\Modules\RegulatoryEngine\Repositories\RegulatoryParameterRepository;
use App\Modules\RegulatoryEngine\Services\OperationalExceptionService;
use App\Modules\RegulatoryEngine\Services\PILACalculationService;
use App\Policies\AffiliatePolicy;
use App\Policies\EmployerPolicy;
use App\Policies\EnrollmentProcessPolicy;
use App\Policies\PilaLiquidationPolicy;
use App\Policies\ReentryProcessPolicy;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Authenticate::redirectUsing(static fn (): string => '/');

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        Gate::policy(Affiliate::class, AffiliatePolicy::class);
        Gate::policy(Employer::class, EmployerPolicy::class);
        Gate::policy(EnrollmentProcess::class, EnrollmentProcessPolicy::class);
        Gate::policy(ReentryProcess::class, ReentryProcessPolicy::class);
        Gate::policy(PilaLiquidation::class, PilaLiquidationPolicy::class);
    }
}
