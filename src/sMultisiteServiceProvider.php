<?php namespace Seiger\sMultisite;

use EvolutionCMS\ServiceProvider;
use Seiger\sMultisite\Facades\sMultisite as SMultisiteFacade;

/**
 * Class sMultisiteServiceProvider
 *
 * Provides services for the sMultisite package, including registering the singleton and alias.
 */
class sMultisiteServiceProvider extends ServiceProvider
{
    /**
     * Register bindings and services in the container.
     *
     * This method is used to bind classes or interfaces into the service container.
     *
     * @return void
     */
    public function register()
    {
        // Register the sMultisite class as a singleton
        $this->app->singleton('sMultisite', fn($app) => new sMultisite());

        // Create an alias for the sMultisite facade
        class_alias(SMultisiteFacade::class, 'sMultisite');

        // Add plugins to Evolution CMS
        $this->loadPluginsFrom(dirname(__DIR__) . '/plugins/');
    }

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

            // Load the package views
            $this->loadViewsFrom(dirname(__DIR__) . '/views', 'sMultisite');

            // Load the package translations
            $this->loadTranslationsFrom(dirname(__DIR__) . '/lang', 'sMultisite');

            // Publish configuration files
            $this->publishes([
                dirname(__DIR__) . '/config/sMultisiteSettings.php' => config_path('seiger/settings/sMultisite.php', true),
                dirname(__DIR__) . '/images/seigerit-blue.svg' => public_path('assets/site/seigerit-blue.svg'),
            ]);

            // Load migration files
            $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');
        }

        // Merge sMultisite configuration
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/sMultisiteCheck.php', 'cms.settings');
    }
}
