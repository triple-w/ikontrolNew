<?php

namespace App\Providers;

use App\Models\CommercialClient;
use App\Models\CommercialQuote;
use App\Policies\CommercialClientPolicy;
use App\Policies\CommercialQuotePolicy;
// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        CommercialClient::class => CommercialClientPolicy::class,
        CommercialQuote::class => CommercialQuotePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
