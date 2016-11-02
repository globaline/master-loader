<?php

namespace Globaline\MasterLoader;

use Illuminate\Support\ServiceProvider;

class LoaderServiceProvider extends ServiceProvider
{

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('loader', 'Globaline\MasterLoader\Loader');
    }
}
