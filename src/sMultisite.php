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
     * Get url from route name with action id
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

        $a = 0;
        $arr = str_split($name, 1);
        foreach ($arr as $n) {
            $a += ord($n);
        }
        $a = $a < 999 ? $a + 999 : $a;

        return $route.'?a='.$a;
    }

    /**
     * Update resiurces tree with domains
     *
     * @return void
     */
    public function domainsTree()
    {
        $domainIds = [];
        $domainBaseIds = evo()->getChildIds(0, 1);
        $domains = \Seiger\sMultisite\Models\sMultisite::all();
        $multisiteResources = $domains->pluck('resource')->toArray();

        if (count($multisiteResources)) {
            $domainBaseIds = array_diff($domainBaseIds, $multisiteResources);
        }
        foreach ($domainBaseIds as $domainBaseId) {
            $domainIds = array_merge($domainIds, evo()->getChildIds($domainBaseId));
        }
        Cache::forget('sMultisite-default-resources');
        Cache::rememberForever('sMultisite-default-resources', function () use ($domainIds) {
            return $domainIds;
        });

        if ($domains) {
            foreach ($domains as $domain) {
                $domainIds = evo()->getChildIds($domain->resource);
                Cache::forget('sMultisite-' . $domain->key . '-resources');
                Cache::rememberForever('sMultisite-' . $domain->key . '-resources', function () use ($domainIds) {
                    return $domainIds;
                });
            }
        }
    }
}