<?php

namespace App\Providers;

use App\Modules\Advisors\Models\Advisor;
use App\Modules\Advisors\Models\AdvisorCommission;
use App\Modules\Affiliates\Commands\BeneficiaryAlertCommand;
use App\Modules\Affiliates\Commands\MoraDetectCommand;
use App\Modules\Affiliates\Commands\TransicionPeriodoCommand;
use App\Modules\Affiliates\Events\ARLRetirementReminderRequested;
use App\Modules\Affiliates\Events\MoraBeneficiaryAlertNeeded;
use App\Modules\Affiliates\Listeners\LogARLRetirementReminder;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\EnrollmentProcess;
use App\Modules\Affiliates\Models\ReentryProcess;
use App\Modules\CashReconciliation\Commands\DailyCloseCommand;
use App\Modules\Communications\Listeners\SendMoraBeneficiaryWhatsApp;
use App\Modules\Communications\Models\CommNotification;
use App\Modules\Disabilities\Models\AffiliateDisability;
use App\Modules\Employers\Models\Employer;
use App\Modules\PILALiquidation\Commands\GenerarPlanillaCommand;
use App\Modules\PILALiquidation\Events\BatchConfirmed;
use App\Modules\PILALiquidation\Events\ContributionSaved;
use App\Modules\PILALiquidation\Listeners\GenerateCuentaCobroOnBatchConfirm;
use App\Modules\PILALiquidation\Listeners\ProcessNoveltiesOnContribution;
use App\Modules\PILALiquidation\Listeners\UpdateMoraStatusOnPayment;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use App\Modules\RegulatoryEngine\Repositories\RegulatoryParameterRepository;
use App\Modules\RegulatoryEngine\Services\MoraInterestService;
use App\Modules\RegulatoryEngine\Services\OperationalExceptionService;
use App\Modules\RegulatoryEngine\Services\PILACalculationService;
use App\Modules\RegulatoryEngine\Services\SolidarityFundCalculator;
use App\Modules\RegulatoryEngine\Strategies\StrategyResolver;
use App\Modules\ThirdParties\Models\AdvisorReceivable;
use App\Modules\ThirdParties\Models\BankDeposit;
use App\Policies\AdvisorCommissionPolicy;
use App\Policies\AdvisorPolicy;
use App\Policies\AdvisorReceivablePolicy;
use App\Policies\AffiliatePolicy;
use App\Policies\BankDepositPolicy;
use App\Policies\CommNotificationPolicy;
use App\Policies\EmployerPolicy;
use App\Policies\EnrollmentProcessPolicy;
use App\Policies\PilaLiquidationPolicy;
use App\Policies\ReentryProcessPolicy;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->commands([
            GenerarPlanillaCommand::class,
            DailyCloseCommand::class,
            TransicionPeriodoCommand::class,
            MoraDetectCommand::class,
            BeneficiaryAlertCommand::class,
        ]);

        $this->app->singleton(PILACalculationService::class, function ($app) {
            $repo = $app->make(RegulatoryParameterRepository::class);

            return new PILACalculationService(
                $app->make(OperationalExceptionService::class),
                $repo,
                $app->make(MoraInterestService::class),
                new SolidarityFundCalculator($repo),
                new StrategyResolver,
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::model('disability', AffiliateDisability::class);
        Route::model('notification', CommNotification::class);

        Authenticate::redirectUsing(static fn (): string => '/');

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        Gate::policy(Advisor::class, AdvisorPolicy::class);
        Gate::policy(AdvisorCommission::class, AdvisorCommissionPolicy::class);
        Gate::policy(BankDeposit::class, BankDepositPolicy::class);
        Gate::policy(AdvisorReceivable::class, AdvisorReceivablePolicy::class);
        Gate::policy(Affiliate::class, AffiliatePolicy::class);
        Gate::policy(Employer::class, EmployerPolicy::class);
        Gate::policy(EnrollmentProcess::class, EnrollmentProcessPolicy::class);
        Gate::policy(ReentryProcess::class, ReentryProcessPolicy::class);
        Gate::policy(PilaLiquidation::class, PilaLiquidationPolicy::class);
        Gate::policy(CommNotification::class, CommNotificationPolicy::class);

        Event::listen(ContributionSaved::class, UpdateMoraStatusOnPayment::class);
        Event::listen(ContributionSaved::class, ProcessNoveltiesOnContribution::class);
        Event::listen(BatchConfirmed::class, GenerateCuentaCobroOnBatchConfirm::class);
        Event::listen(ARLRetirementReminderRequested::class, LogARLRetirementReminder::class);
        Event::listen(MoraBeneficiaryAlertNeeded::class, SendMoraBeneficiaryWhatsApp::class);
    }
}
