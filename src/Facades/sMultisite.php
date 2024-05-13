<?php namespace Seiger\sMultisite\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class sMultisite
 *
 * @package Seiger\sMultisite
 * @mixin \Seiger\sMultisite\sMultisite
 */
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