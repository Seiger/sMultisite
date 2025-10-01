<?php namespace Seiger\sMultisite;

use EvolutionCMS\Facades\UrlProcessor;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Support\Facades\Cache;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sMultisite\Models\sMultisite as SMultisiteModel;

/**
 * Class sMultisite
 *
 * Provides methods for managing multisite configurations.
 *
 * @package Seiger\sMultisite
 */
class sMultisite
{
    /**
     * Show all active domains.
     *
     * @return array
     */
    public function domains(): array
    {
        // Initialize the default domain
        $domains['default'] = [
            'key' => 'default',
            'link' => $this->scheme(evo()->getConfig('server_protocol', 'https') . '://' . $_SERVER['HTTP_HOST']),
            'site_name' => evo()->getConfig('site_name', 'Evolution CMS'),
            'is_current' => true,
        ];

        // Retrieve all active multisite records
        $items = SMultisiteModel::whereActive(1)->get();

        // Process each active multisite record
        foreach ($items as $item) {
            $domains[$item->key] = [
                'key' => $item->key,
                'link' => $this->scheme(evo()->getConfig('server_protocol', 'https') . '://' . $item->domain),
                'site_name' => $item->site_name,
                'is_current' => ($_SERVER['HTTP_HOST'] === $item->domain),
            ];
        }

        return $domains;
    }

    /**
     * Generate a URL from the route name with an action ID appended.
     *
     * @param string $name Route name
     * @return string
     */
    public function route(string $name): string
    {
        // Generate the base route URL and remove trailing slashes
        $route = rtrim(route($name), '/');
        $friendlyUrlSuffix = evo()->getConfig('friendly_url_suffix', '');

        // Remove friendly URL suffix if it's not a slash
        if ($friendlyUrlSuffix !== '/') {
            $route = str_ireplace($friendlyUrlSuffix, '', $route);
        }

        // Generate a unique action ID based on the route name
        $a = array_sum(array_map('ord', str_split($name))) + 999;
        $a = $a < 999 ? $a + 999 : $a;

        // Return the route URL with the action ID appended
        return $this->scheme($route) . '?a=' . $a;
    }

    /**
     * Determine the URL scheme (HTTP or HTTPS) based on server variables.
     *
     * @param string $url URL to modify
     * @return string
     */
    public function scheme(string $url): string
    {
        // Determine the current scheme from various sources
        $scheme = 'http'; // Default to HTTP

        // Check the server variables for HTTPS
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        }
        // Check the forward headers if present
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            $scheme = 'https';
        }
        // Check the HTTP_HOST for known HTTPS setups
        elseif (isset($_SERVER['HTTP_HOST']) && preg_match('/^https:/i', $_SERVER['HTTP_HOST'])) {
            $scheme = 'https';
        }
        // Check if the server is using HTTPS by default
        elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            $scheme = 'https';
        }

        // Replace the scheme in the URL if necessary
        return preg_replace('/^http:\/\//', $scheme . '://', $url);
    }

    /**
     * Update resources tree with domains and cache the results.
     *
     * @return void
     */
    public function domainsTree(): void
    {
        $resources = SiteContent::all();
        foreach ($resources as $row) {
            if ((bool)evo()->getConfig('use_alias_path') && $row->parent > 0) {
                $parent = $row->parent;
                $path = $allAliases[$parent];

                while (isset(UrlProcessor::getFacadeRoot()->aliasListing[$parent]) && (int)UrlProcessor::getFacadeRoot()->aliasListing[$parent]['alias_visible'] === 0) {
                    $path = UrlProcessor::getFacadeRoot()->aliasListing[$parent]['path'];
                    $parent = UrlProcessor::getFacadeRoot()->aliasListing[$parent]['parent'];
                }
                $allAliases[$row->getKey()] = $path . '/' . $row->alias;
            } else {
                $allAliases[$row->getKey()] = $row->alias;
            }
        }

        $domains = SMultisiteModel::all();
        $multisiteResources = $domains->pluck('resource')->toArray();

        // Exclude existing multisite resources from the base IDs
        if (count($multisiteResources)) {
            foreach ($multisiteResources as $mr) {
                unset($allAliases[$mr]);
            }
        }

        // Cache resources for each domain
        foreach ($domains as $domain) {
            if ($domain->key === 'default') {
                $domainBaseIds = evo()->getChildIds(0, 1);
                foreach ($domainBaseIds as $als => $dbid) {
                    if (!in_array($dbid, $multisiteResources)) {
                        $domainIds[$als] = $dbid;
                        $domainIds = array_merge($domainIds, evo()->getChildIds($dbid));
                    }
                }
            } else {
                $domainIds = evo()->getChildIds($domain->resource);
            }

            $aliases = [];
            foreach ($domainIds as $id) {
                $aliases[trim($allAliases[$id] ?? $id, '/')] = $id;
            }

            Cache::forget('sMultisite-' . $domain->key . '-resources');
            Cache::rememberForever('sMultisite-' . $domain->key . '-resources', fn() => $aliases);
        }
    }
}
