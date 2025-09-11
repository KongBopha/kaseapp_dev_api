<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\FileUploadService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(FileUploadService::class, function ($app) {
            return new FileUploadService();
        });    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
