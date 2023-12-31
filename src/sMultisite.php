<?php namespace Seiger\sMultisite;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class sMultisite
{
    /**
     * Show all active domains
     *
     * @return array
     */
    public function domains(): array
    {
        $domains['default'] = [
            'key' => 'default',
            'link' => evo()->getConfig('server_protocol', 'https') . '://' . $_SERVER['HTTP_HOST'],
            'site_name' => evo()->getConfig('site_name', 'Evolution CMS'),
            'is_current' => true,
        ];

        $items = \Seiger\sMultisite\Models\sMultisite::whereActive(1)->get();
        if ($items) {
            foreach ($items as $item) {
                $domains[$item->key] = [
                    'key' => $item->key,
                    'link' => evo()->getConfig('server_protocol', 'https') . '://' . $item->domain,
                    'site_name' => $item->site_name,
                    'is_current' => ($_SERVER['HTTP_HOST'] == $item->domain),
                ];
            }
        }

        return $domains;
    }

    /**
     * Get url from route name
     *
     * @param string $name Route name
     * @return string
     */
    public function route(string $name): string
    {
        $route = rtrim(route($name), '/');
        if (evo()->getConfig('friendly_url_suffix', '') != '/') {
            $route = str_ireplace(evo()->getConfig('friendly_url_suffix', ''), '', route($name));
        }
        return $route . '/';
    }

    /**
     * Update resiurces tree with domains
     *
     * @return void
     */
    public function domainsTree()
    {
        $domains = \Seiger\sMultisite\Models\sMultisite::all();
        $domainBaseIds = evo()->getChildIds(0, 1);
        $multisiteResources = $domains->pluck('resource')->toArray();
        if (count($multisiteResources)) {
            $domainBaseIds = array_diff($domainBaseIds, $multisiteResources);

            foreach ($domains as $domain) {
                $domainIds = evo()->getChildIds($domain->resource);
                Cache::rememberForever('sMultisite-' . $domain->key . '-resources', function () use ($domainIds) {
                    return $domainIds;
                });
            }
        }

        $domainDefaultIds = $domainBaseIds;
        foreach ($domainBaseIds as $domainBaseId) {
            $domainDefaultIds = array_merge($domainDefaultIds, evo()->getChildIds($domainBaseId));
        }
        Cache::rememberForever('sMultisite-default-resources', function () use ($domainDefaultIds) {
            return $domainDefaultIds;
        });
    }
}