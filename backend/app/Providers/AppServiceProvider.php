<?php

namespace App\Providers;

use App\Contracts\TransactionCategorizer;
use App\Services\RulesAndHeuristicsCategorizer;
use App\Support\DemoUserContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TransactionCategorizer::class, RulesAndHeuristicsCategorizer::class);
        $this->app->scoped(DemoUserContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
