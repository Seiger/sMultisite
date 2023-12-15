<?php namespace Seiger\sMultisite;

use EvolutionCMS\ServiceProvider;

class sMultisiteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Only Manager
        if (IN_MANAGER_MODE) {
            // Add custom routes for package
            include(__DIR__.'/Http/routes.php');

            // Migration for create tables
            $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');

            // Views
            $this->loadViewsFrom(dirname(__DIR__) . '/views', 'sMultisite');

            // MultiLang
            $this->loadTranslationsFrom(dirname(__DIR__) . '/lang', 'sMultisite');

            // Files
            $this->publishes([
                dirname(__DIR__) . '/config/sMultisiteAlias.php' => config_path('app/aliases/sMultisite.php', true),
                dirname(__DIR__) . '/config/sMultisiteSettings.php' => config_path('seiger/settings/sMultisite.php', true),
                dirname(__DIR__) . '/images/seigerit-blue.svg' => public_path('assets/site/seigerit-blue.svg'),
            ]);
        }

        // Class alias
        $this->app->singleton(\Seiger\sMultisite\sMultisite::class);
        $this->app->alias(\Seiger\sMultisite\sMultisite::class, 'sMultisite');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Add plugins to Evo
        $this->loadPluginsFrom(dirname(__DIR__) . '/plugins/');
    }
}