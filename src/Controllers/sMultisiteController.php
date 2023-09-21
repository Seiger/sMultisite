<?php namespace Seiger\sMultisite\Controllers;

use EvolutionCMS\Models\SiteContent;
use Illuminate\Support\Str;
use Seiger\sMultisite\Models\sMultisite;
use View;

class sMultisiteController
{
    /**
     * Show tabs with custom system settings
     *
     * @return View
     */
    public function index()
    {
        return $this->view('index');
    }

    public function update()
    {
        $refresh = false;

        if (request()->has('new-domains') && is_array(request()->input('new-domains', [])) && count(request()->input('new-domains', []))) {
            foreach (request()->input('new-domains', []) as $item) {
                $item = trim($item, 'https://');
                $item = trim($item, 'http://');
                $item = trim($item, '/');
                $find = sMultisite::whereDomain($item)->first();
                if (!$find) {
                    // Base resource
                    $resource = new SiteContent();
                    $resource->pagetitle = $item;
                    $resource->content = 'https://'.$item;
                    $resource->type = 'reference';
                    $resource->parent = 0;
                    $resource->alias_visible = 0;
                    $resource->richtext = 0;
                    $resource->published = 1;
                    $resource->isfolder = 1;
                    $resource->hidemenu = 1;
                    $resource->save();

                    // Home resource
                    $homepage = new SiteContent();
                    $homepage->pagetitle = 'Homepage';
                    $homepage->published = 1;
                    $homepage->parent = $resource->id;
                    $homepage->save();

                    // Domain
                    $domain = new sMultisite();
                    $domain->domain = $item;
                    $domain->key = $this->validateKey($item);
                    $domain->resource = $resource->id;
                    $domain->site_start = $homepage->id;
                    $domain->error_page = $homepage->id;
                    $domain->unauthorized_page = $homepage->id;
                    $domain->save();
                }
            }
        }

        if (request()->has('domains') && is_array(request()->input('domains', [])) && count(request()->input('domains', []))) {
            foreach (request()->input('domains', []) as $id => $item) {
                if (is_array($item) && count($item)) {
                    $domain = sMultisite::find($id);
                    if ($domain) {
                        foreach ($item as $name => $value) {
                            $domain->{$name} = $value;
                        }
                        $domain->update();

                        if (is_array($domain->getChanges()) && isset($domain->getChanges()['hide_from_tree'])) {
                            $refresh = true;
                        }
                    }
                }
            }
        }

        if ($refresh) {
            return header("Location: /".MGR_DIR."/index.php?a=7&r=10");
        } else {
            return back();
        }
    }

    /**
     * Alias validation
     *
     * @param $data
     * @param string $table
     * @return string
     */
    private function validateKey($string = '', $id = 0): string
    {
        if (trim($string)) {
            $alias = explode('.', $string);
            array_pop($string);
            $alias = Str::slug(trim(implode('', $alias)));
        } else {
            $alias = $id;
        }

        $aliases = sMultisite::where('s_multisites.id', '<>', $id)->get('key')->pluck('key')->toArray();

        if (in_array($alias, $aliases)) {
            $cnt = 1;
            $tempAlias = $alias;
            while (in_array($tempAlias, $aliases)) {
                $tempAlias = $alias . $cnt;
                $cnt++;
            }
            $alias = $tempAlias;
        }
        return $alias;
    }

    /**
     * Display render
     *
     * @param string $tpl
     * @param array $data
     * @return View
     */
    public function view(string $tpl, array $data = [])
    {
        return View::make('sMultisite::'.$tpl, $data);
    }
}
