<?php

namespace Globaline\MasterLoader;

use Illuminate\Support\ServiceProvider;

class LoaderServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('loader', function ($model) {
            return new Loader($model);
        });

        $this->app->alias('loader', 'Globaline\MasterLoader\Loader');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['loader', 'Globaline\MasterLoader\Loader',];
    }
}
