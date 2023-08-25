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
            // Files
            $this->publishes([
                dirname(__DIR__) . '/config/sMultisiteAlias.php' => config_path('app/aliases/sMultisite.php', true),
                dirname(__DIR__) . '/config/sMultisiteSettings.php' => config_path('seiger/settings/sMultisite.php', true),
                dirname(__DIR__) . '/images/seigerit-yellow.svg' => public_path('assets/site/seigerit-yellow.svg'),
            ]);
        }

        // Class alias
        $this->app->singleton(\Seiger\sMultisite\sMultisite::class);
        $this->app->alias(\Seiger\sMultisite\sMultisite::class, 'sMultisite');
    }
}