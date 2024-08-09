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

    /**
     * Update domain settings
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update()
    {
        $newDomains = request()->input('new-domains', []);
        $existingDomains = request()->input('domains', []);
        $this->processNewDomains($newDomains);
        $this->updateExistingDomains($existingDomains);
        $_SESSION['sMultisite.refresh'] = true;
        return header("Location: " . \Seiger\sMultisite\Facades\sMultisite::route('sMultisite.index'));
    }

    /**
     * Process new domains and save them
     *
     * @param array $domains
     * @return bool
     */
    private function processNewDomains(array $domains): bool
    {
        $refresh = false;
        foreach ($domains as $item) {
            $item = $this->sanitizeDomain($item);
            if (!$this->domainExists($item)) {
                $this->createDomainResources($item);
            }
        }
        return $refresh;
    }

    /**
     * Update existing domain settings
     *
     * @param array $domains
     * @return bool
     */
    private function updateExistingDomains(array $domains): bool
    {
        $refresh = false;
        foreach ($domains as $id => $item) {
            if (is_array($item) && $domain = sMultisite::find($id)) {
                if ($item['key'] != 'default') {
                    $item['active'] = intval($item['active'] ?? 0);
                    $item['hide_from_tree'] = intval($item['hide_from_tree'] ?? 0);
                }
                $domain->update($item);
                if (isset($domain->getChanges()['hide_from_tree'])) {
                    $refresh = true;
                }
            }
        }
        return $refresh;
    }

    /**
     * Create resources for a new domain
     *
     * @param string $item
     */
    private function createDomainResources(string $item): void
    {
        $resource = new SiteContent();
        $resource->fill([
            'pagetitle' => $item,
            'content' => 'https://' . $item,
            'type' => 'reference',
            'parent' => 0,
            'alias_visible' => 0,
            'richtext' => 0,
            'published' => 1,
            'isfolder' => 1,
            'hidemenu' => 1,
        ]);
        $resource->save();

        $homepage = new SiteContent();
        $homepage->fill([
            'pagetitle' => 'Homepage',
            'published' => 1,
            'parent' => $resource->id,
        ]);
        $homepage->save();

        $domain = new sMultisite();
        $domain->fill([
            'domain' => $item,
            'key' => $this->validateKey($item),
            'resource' => $resource->id,
            'site_name' => 'Evolution CMS website',
            'site_start' => $homepage->id,
            'error_page' => $homepage->id,
            'unauthorized_page' => $homepage->id,
        ]);
        $domain->save();
    }

    /**
     * Check if a domain already exists
     *
     * @param string $item
     * @return bool
     */
    private function domainExists(string $item): bool
    {
        return sMultisite::whereDomain($item)->exists();
    }

    /**
     * Sanitize domain input
     *
     * @param mixed $item
     * @return string
     */
    private function sanitizeDomain($item): string
    {
        // Convert array to string if needed
        $item = is_array($item) ? implode('-', $item) : $item;

        // Remove protocols and trailing slashes
        $item = preg_replace(['/^(https?:\/\/)/'], '', $item);

        return trim($item, '/');
    }

    /**
     * Validate and generate a unique key for a domain
     *
     * @param string $string
     * @param int $id
     * @return string
     */
    private function validateKey(string $string = '', int $id = 0): string
    {
        $alias = $this->generateAlias($string);
        $existingAliases = sMultisite::where('s_multisites.id', '<>', $id)
            ->pluck('key')
            ->toArray();

        return $this->resolveAliasConflict($alias, $existingAliases);
    }

    /**
     * Generate a slug-like alias from the domain string
     *
     * @param string $string
     * @return string
     */
    private function generateAlias(string $string): string
    {
        if (trim($string)) {
            $alias = explode('.', $string);
            array_pop($alias);
            return Str::slug(trim(implode('', $alias)));
        }
        return '';
    }

    /**
     * Resolve alias conflicts by appending a numeric suffix
     *
     * @param string $alias
     * @param array $existingAliases
     * @return string
     */
    private function resolveAliasConflict(string $alias, array $existingAliases): string
    {
        if (in_array($alias, $existingAliases)) {
            $cnt = 1;
            $tempAlias = $alias;
            while (in_array($tempAlias, $existingAliases)) {
                $tempAlias = $alias . $cnt;
                $cnt++;
            }
            return $tempAlias;
        }
        return $alias;
    }

    /**
     * Render a view with optional data
     *
     * @param string $tpl
     * @param array $data
     * @return View
     */
    public function view(string $tpl, array $data = [])
    {
        return View::make('sMultisite::' . $tpl, $data);
    }
}
