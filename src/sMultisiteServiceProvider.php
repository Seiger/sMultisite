<?php namespace Seiger\sMultisite;

use EvolutionCMS\ServiceProvider;
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
        $this->publishes([
            dirname(__DIR__) . '/config/sMultisiteSettings.php' => config_path('seiger/settings/sMultisite.php', true),
            dirname(__DIR__) . '/images/seigerit-blue.svg' => public_path('assets/site/seigerit-blue.svg'),
        ]);
    }
}
