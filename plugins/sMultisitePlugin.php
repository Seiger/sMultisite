<?php
/**
 * Plugin for Seiger Multisite Tools to Evolution CMS.
 */

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Seiger\sMultisite\Facades\sMultisite;

/**
 * Load start parameters
 */
Event::listen('evolution.OnLoadSettings', function() {
    evo()->setConfig('site_key', 'default');
    evo()->setConfig('site_root', 0);
    if (evo()->isFrontend()) {
        $domain = \Seiger\sMultisite\Models\sMultisite::whereDomain($_SERVER['HTTP_HOST'])->whereActive(1)->first();
        if ($domain) {
            evo()->setConfig('site_key', $domain->key);
            evo()->setConfig('site_name', $domain->site_name);
            evo()->setConfig('site_start', $domain->site_start);
            evo()->setConfig('error_page', $domain->error_page);
            evo()->setConfig('unauthorized_page', $domain->unauthorized_page);
            evo()->setConfig('site_root', (int)$domain->resource);
        }
    }
    $aliasListing = Cache::get('sMultisite-' . evo()->getConfig('site_key', 'default') . '-resources') ?? [];
    if (is_array($aliasListing)) {
        evo()->documentListing = $aliasListing;
    }
});

/**
 * Correcting urls
 */
Event::listen('evolution.OnWebPageInit', function() {
    $domainIds = Cache::get('sMultisite-' . evo()->getConfig('site_key', 'default') . '-resources') ?? [];
    if (!in_array(evo()->documentIdentifier, $domainIds)) {
        evo()->sendErrorPage();
    }
});

/**
 * Correcting urls
 */
Event::listen('evolution.OnMakeDocUrl', function($params) {
    if (evo()->isBackend()) {
        $roots = evo()->getParentIds($params['id']);
        if (count($roots)) {
            $root = array_pop($roots);

            $domainUrl = Cache::rememberForever('sMultisite-' . $root . '-url', function () use ($root) {
                $url = '';
                $domain = \Seiger\sMultisite\Models\sMultisite::whereResource($root)->first();
                if ($domain) {
                    $url = evo()->getConfig('server_protocol', 'https') . '://' . $domain->domain;
                }
                return $url;
            });

            return $domainUrl . $params['url'];
        }
    }
});

/**
 * Correcting cache
 */
Event::listen('evolution.OnMakePageCacheKey', function($params) {
    return evo()->getConfig('site_key', 'default') . '_' . $params['hash'];
});

/**
 * Update multidomain tree
 */
Event::listen('evolution.OnCacheUpdate', function($params) {
    sMultisite::domainsTree();
});

/**
 * Add Menu item
 */
Event::listen('evolution.OnManagerMenuPrerender', function($params) {
    if (evo()->hasPermission('settings')) {
        $menu['smultisite'] = [
            'smultisite',
            'tools',
            '<i class="' . __('sMultisite::global.icon') . '"></i><span class="menu-item-text">' . __('sMultisite::global.title') . '</span>',
            sMultisite::route('sMultisite.index'),
            __('sMultisite::global.title'),
            "",
            "",
            "main",
            0,
            8,
        ];

        return serialize(array_merge($params['menu'], $menu));
    }
});

/**
 * Present domains
 */
Event::listen('evolution.OnManagerTreeRender', function($params) {
    $tree = '';
    $_style = ManagerTheme::getStyle();
    $domains = \Seiger\sMultisite\Models\sMultisite::where('hide_from_tree', 0)->whereNot('key', 'default')->get();
    if ($domains) {
        foreach ($domains as $domain) {
            $tree .= '<div id="node'.$domain->resource.'" class="rootNode" style="position:initial;">';
            $tree .= '<a class="node" onclick="modx.tree.treeAction(event, '.$domain->resource.')" data-id="'.$domain->resource.'" data-title-esc="'.$domain->site_name.'">';
            $tree .= '<span class="icon"><i class="'.$_style['icon_sitemap'].'"></i></span>';
            if ($domain->active) {
                $tree .= '<span class="title">';
            } else {
                $tree .= '<span class="title" style="color:#d0726b;">';
            }
            $tree .= $domain->site_name.'</span></a></div>';
            $tree .= '<div id="treeRoot'.$domain->resource.'" class="treeRoot"></div>';
        }
        return $tree;
    }
});

/**
 * Resources manipulation
 */
Event::listen('evolution.OnManagerNodePrerender', function($params) {
    $domains = \Seiger\sMultisite\Models\sMultisite::where('hide_from_tree', 0)->whereNot('key', 'default')->get();
    if ($domains) {
        $_style = ManagerTheme::getStyle();
        $startResources = $domains->pluck('site_start')->toArray();
        $errorResources = $domains->pluck('error_page')->toArray();
        $unauthorizedResources = $domains->pluck('unauthorized_page')->toArray();
        switch (true) {
            case in_array($params['ph']['id'], $startResources) :
                $params['ph']['icon'] = '<i class="' . $_style['icon_home'] . '"></i>';
                $params['ph']['nomove'] = '1';
                break;
            case in_array($params['ph']['id'], $errorResources) :
                $params['ph']['icon'] = '<i class="' . $_style['icon_info_triangle'] . '"></i>';
                $params['ph']['nomove'] = '1';
                break;
            case in_array($params['ph']['id'], $unauthorizedResources) :
                $params['ph']['icon'] = '<i class="' . $_style['unauthorized_page'] . '"></i>';
                $params['ph']['nomove'] = '1';
                break;
        }
        return serialize($params['ph']);
    }
});

/**
 * Hide domain resources
 */
Event::listen('evolution.OnManagerNodeRender', function($params) {
    $domains = \Seiger\sMultisite\Models\sMultisite::all();
    if ($domains) {
        $multisiteResources = $domains->pluck('resource')->toArray();
        if (is_array($multisiteResources) && in_array($params['id'], $multisiteResources)) {
            return ' ';
        }
    }
});
