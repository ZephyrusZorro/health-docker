<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Hash;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Enforce bcrypt cost 12 (SRS §12.2)
        Hash::setRounds(config('hms.security.bcrypt_cost', 12));

        // Block accidental mass-assignment bypass in non-prod
        Model::shouldBeStrict(! $this->app->isProduction());

        // Force HTTPS for signed URLs in production (S3 presigned URLs)
        if ($this->app->isProduction()) {
            URL::forceHttps();
        }
    }
}
