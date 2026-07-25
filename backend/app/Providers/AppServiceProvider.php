<?php

namespace App\Providers;

use App\Contracts\TransactionCategorizer;
use App\Services\RulesAndHeuristicsCategorizer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TransactionCategorizer::class, RulesAndHeuristicsCategorizer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
