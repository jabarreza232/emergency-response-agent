<?php

namespace App\Providers;

use App\Repositories\Contracts\EmergencyContactRepositoryInterface;
use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\HospitalRepositoryInterface;
use App\Repositories\Eloquent\HospitalRepository;
use App\Repositories\Eloquent\EmergencyContactRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            HospitalRepositoryInterface::class,
            HospitalRepository::class,

        );
        $this->app->bind(
            EmergencyContactRepositoryInterface::class,
            EmergencyContactRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
