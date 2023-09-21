<?php namespace Seiger\sMultisite\Facades;

use Illuminate\Support\Facades\Facade;

class sMultisite extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sMultisite';
    }
}