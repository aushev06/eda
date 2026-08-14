<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Observers\LoyaltyAccountObserver;
use App\Observers\LoyaltyTransactionObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        LoyaltyTransaction::observe(LoyaltyTransactionObserver::class);
        LoyaltyAccount::observe(LoyaltyAccountObserver::class);

        Gate::define('pos', fn (User $user): bool => in_array(
            $user->role,
            [UserRole::Admin, UserRole::Staff],
            true,
        ));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
