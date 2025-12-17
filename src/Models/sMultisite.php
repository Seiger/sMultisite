<?php namespace Seiger\sMultisite\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class sMultisite
 *
 * Represents a multisite instance in the Evolution CMS.
 *
 * @package Seiger\sMultisite\Models
 * @property int $id
 * @property int $active
 * @property int $hide_from_tree
 * @property int $resource
 * @property string $key
 * @property string $domain
 * @property string $site_name
 * @property int $site_start
 * @property int $error_page
 * @property int $unauthorized_page
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class sMultisite extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 's_multisites';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'active',
        'hide_from_tree',
        'resource',
        'key',
        'domain',
        'site_name',
        'site_start',
        'error_page',
        'unauthorized_page',
        'site_color',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'active' => 'integer',
        'hide_from_tree' => 'integer',
        'resource' => 'integer',
        'site_start' => 'integer',
        'error_page' => 'integer',
        'unauthorized_page' => 'integer'
    ];
}
