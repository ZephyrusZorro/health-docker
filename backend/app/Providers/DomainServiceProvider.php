<?php

namespace App\Providers;

use App\Services\Identity\UhidGenerator;
use App\Services\Scheduling\SlotGenerator;
use App\Services\Scheduling\TokenGenerator;
use App\Services\Pharmacy\FefoDispenser;
use App\Services\Billing\InvoiceAggregator;
use App\Services\Clinical\VitalsRecorder;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the HMS domain services as singletons. Controllers receive these via
 * constructor injection, keeping business logic out of HTTP handlers.
 */
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UhidGenerator::class);
        $this->app->singleton(SlotGenerator::class);
        $this->app->singleton(TokenGenerator::class);
        $this->app->singleton(FefoDispenser::class);
        $this->app->singleton(InvoiceAggregator::class);
        $this->app->singleton(VitalsRecorder::class);
        $this->app->singleton(NotificationDispatcher::class);
    }

    public function boot(): void
    {
        //
    }
}
