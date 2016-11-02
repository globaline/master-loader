<?php

namespace Globaline\MasterLoader;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Globaline\MasterLoader\Loader
 */
class LoaderFacade extends Facade
{

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'loader';
    }
}
