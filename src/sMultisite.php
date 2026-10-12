<?php namespace Seiger\sMultisite;

use EvolutionCMS\Models\SiteContent;
use Illuminate\Support\Facades\Cache;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sMultisite\Models\sMultisite as SMultisiteModel;

/**
 * Class sMultisite
 *
 * Provides domain navigation and rebuilds the frontend resource maps per domain.
 * Resource paths are resolved from their ancestry rather than database row order.
 *
 * @package Seiger\sMultisite
 */
class sMultisite
{
    /**
     * Return the default site and all active multisite domains.
     *
     * HTTP requests use the current host to identify the active domain. Console
     * commands have no request host, so they use localhost as a neutral fallback
     * while the default site remains current.
     *
     * @return array<string, array{key: string, link: string, site_name: string, is_current: bool}>
     */
    public function domains(): array
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // Initialize the default domain
        $domains['default'] = [
            'key' => 'default',
            'link' => $this->scheme(evo()->getConfig('server_protocol', 'https') . '://' . $host),
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
                'is_current' => ($host === $item->domain),
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
     * Rebuild the frontend URL and resource-access maps for every domain.
     *
     * Resolve all resource paths before replacing cached maps, so moved resources
     * remain addressable even when their parent was created after them.
     *
     * @return void
     */
    public function domainsTree(): void
    {
        $allAliases = $this->resourceAliases(SiteContent::all(), (bool)evo()->getConfig('use_alias_path'));

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

    /**
     * Resolve resource paths independently of the order returned by the database.
     *
     * Hidden ancestors are omitted from descendant URLs, while their own resource
     * alias remains addressable. Invalid ancestry aborts the rebuild before any
     * domain cache is replaced rather than publishing truncated paths.
     *
     * @param iterable<SiteContent> $resources Complete resource tree, including domain containers.
     * @param bool $useAliasPath Whether friendly URLs include visible ancestors.
     * @return array<int, string> Full path indexed by resource ID.
     * @throws \LogicException When ancestry is cyclic or references a missing resource.
     * @since 1.2.0
     */
    protected function resourceAliases(iterable $resources, bool $useAliasPath): array
    {
        $byId = [];
        foreach ($resources as $resource) {
            $byId[(int)$resource->getKey()] = $resource;
        }

        $aliases = [];
        $resolving = [];
        $resolve = function (int $id) use (&$resolve, &$aliases, &$resolving, $byId, $useAliasPath): string {
            if (array_key_exists($id, $aliases)) {
                return $aliases[$id];
            }
            if (isset($resolving[$id]) || !isset($byId[$id])) {
                throw new \LogicException('Cannot resolve resource ancestry for ID ' . $id);
            }

            $resolving[$id] = true;
            $resource = $byId[$id];
            $path = '';
            $parent = (int)$resource->parent;
            if ($useAliasPath && $parent > 0) {
                $path = $resolve($parent);
                while ($parent > 0 && (int)($byId[$parent]->alias_visible ?? 1) === 0) {
                    $parent = (int)$byId[$parent]->parent;
                    $path = $parent > 0 ? $resolve($parent) : '';
                }
            }

            unset($resolving[$id]);
            return $aliases[$id] = ($path === '' ? '' : $path . '/') . (string)$resource->alias;
        };

        foreach (array_keys($byId) as $id) {
            $resolve($id);
        }

        return $aliases;
    }
}
