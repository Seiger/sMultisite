<?php namespace Seiger\sMultisite;

use Illuminate\Support\Facades\Cache;
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
        return preg_replace('/^http/', $scheme, $url);
    }

    /**
     * Update resources tree with domains and cache the results.
     *
     * @return void
     */
    public function domainsTree(): void
    {
        $domainIds = [];
        $domainBaseIds = evo()->getChildIds(0, 1);
        $domains = SMultisiteModel::all();
        $multisiteResources = $domains->pluck('resource')->toArray();

        // Exclude existing multisite resources from the base IDs
        if (count($multisiteResources)) {
            $domainBaseIds = array_diff($domainBaseIds, $multisiteResources);
        }

        // Collect all resource IDs for the domains
        foreach ($domainBaseIds as $domainBaseId) {
            $domainIds = array_merge($domainIds, evo()->getChildIds($domainBaseId));
        }

        // Cache default resources
        Cache::forget('sMultisite-default-resources');
        Cache::rememberForever('sMultisite-default-resources', fn() => $domainIds);

        // Cache resources for each domain
        foreach ($domains as $domain) {
            $domainIds = evo()->getChildIds($domain->resource);
            Cache::forget('sMultisite-' . $domain->key . '-resources');
            Cache::rememberForever('sMultisite-' . $domain->key . '-resources', fn() => $domainIds);
        }
    }
}
