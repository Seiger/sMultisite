<?php namespace Seiger\sMultisite;

use EvolutionCMS\Facades\Console;
use EvolutionCMS\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Seiger\sMultisite\Console\PublishAssets;
use Seiger\sMultisite\Facades\sMultisite as sMultisiteFacade;

/**
 * Class sMultisiteServiceProvider
 *
 * Provides services for the sMultisite package, including registering the singleton and alias.
 */
class sMultisiteServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * This method is used to perform any tasks required after the services are registered.
     *
     * @return void
     */
    public function boot()
    {
        // Check if in Manager mode
        if (IN_MANAGER_MODE) {
            // Load the package routes
            $this->loadRoutesFrom(__DIR__.'/Http/routes.php');

            // Load migrations, views, translations only if necessary
            $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');
            $this->loadViewsFrom(dirname(__DIR__) . '/views', 'sMultisite');
            $this->loadTranslationsFrom(dirname(__DIR__) . '/lang', 'sMultisite');

            // Publish configuration and assets
            $this->publishResources();
        }

        // Merge configuration for sMultisite
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/sMultisiteCheck.php', 'cms.settings');

        // Register sGallery as a singleton using the key 'sGallery'
        $this->app->singleton('sMultisite', fn($app) => new sMultisite());

        // Create class alias for the facade
        class_alias(sMultisiteFacade::class, 'sMultisite');

        // Update active css and js and correcting version
        if ($this->app->runningInConsole()) {
            if (in_array('package:discover', $_SERVER['argv'] ?? [], true)) {
                static $done = false;
                if ($done) return;
                $done = true;

                $current = 'dev-master';
                try {
                    $current = \Composer\InstalledVersions::getVersion('seiger/smultisite') ?? 'dev-master';
                    $current = rtrim($current, '.0');
                } catch (\Throwable) {}

                $last = null;
                try {
                    $last = evo()->getConfig('sMultisiteVer');
                } catch (\Throwable) {}

                if ($current !== $last || $current == 'dev-master') {
                    try {
                        Console::call('smultisite:publish');
                    } catch (\Throwable $e) {
                        Log::info('sMultisite auto-publish failed: ' . $e->getMessage(), 'sMultisite');
                    }
                }
            }
        }
    }

    /**
     * Register bindings and services in the container.
     *
     * This method is used to bind classes or interfaces into the service container.
     *
     * @return void
     */
    public function register()
    {
        // Add plugins to Evolution CMS
        $this->loadPluginsFrom(dirname(__DIR__) . '/plugins/');
    }

    /**
     * Publish the necessary resources for the package.
     *
     * @return void
     */
    protected function publishResources()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PublishAssets::class]);
        }

        $this->publishes([
            dirname(__DIR__) . '/config/sMultisiteSettings.php' => config_path('seiger/settings/sMultisite.php', true),
            dirname(__DIR__) . '/images/seigerit.svg' => public_path('assets/site/seigerit.svg'),
            dirname(__DIR__) . '/images/logo.svg' => public_path('assets/site/smultisite.svg'),
            dirname(__DIR__) . '/css/tailwind.min.css' => public_path('assets/site/smultisite.min.css'),
            dirname(__DIR__) . '/js/main.js' => public_path('assets/site/smultisite.js'),
            dirname(__DIR__) . '/js/tooltip.js' => public_path('assets/site/seigerit.tooltip.js'),
        ]);
    }
}
