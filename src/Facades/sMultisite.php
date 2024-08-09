<?php namespace Seiger\sMultisite\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class sMultisite
 *
 * Facade for accessing the sMultisite service.
 *
 * @package Seiger\sMultisite
 * @mixin \Seiger\sMultisite\sMultisite
 */
class sMultisite extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * This method should return the name of the component being accessed by the facade.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sMultisite';
    }
}
