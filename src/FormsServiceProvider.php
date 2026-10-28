<?php

namespace Xelqun\Forms;

use Illuminate\Support\ServiceProvider;
use Xelqun\Forms\Commands\PruneTemporaryUploadsCommand;
use Xelqun\Forms\Uploads\TemporaryUploadStore;

class FormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/xelqun-forms.php', 'xelqun-forms');

        $this->app->singleton(TemporaryUploadStore::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/xelqun-forms.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/xelqun-forms.php' => config_path('xelqun-forms.php'),
            ], 'xelqun-forms-config');

            $this->commands([PruneTemporaryUploadsCommand::class]);
        }
    }
}
