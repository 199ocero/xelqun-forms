<?php

namespace Xelqun\Forms;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\ServiceProvider;
use Xelqun\Forms\Commands\InstallCommand;
use Xelqun\Forms\Commands\InstallPluginCommand;
use Xelqun\Forms\Commands\MakeFieldCommand;
use Xelqun\Forms\Commands\MakeFormCommand;
use Xelqun\Forms\Commands\PruneTemporaryUploadsCommand;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Forms\Uploads\TemporaryUploadStore;
use Xelqun\Notifications\NotificationQueue;

class FormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/xelqun-forms.php', 'xelqun-forms');

        $this->app->singleton(FormTokenEncrypter::class);
        $this->app->singleton(TemporaryUploadStore::class);
        $this->app->scoped(NotificationQueue::class);

        // Laravel's own list only covers top-level keys, and a form posts everything under data.
        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if ($handler instanceof Handler) {
                $handler->dontFlash('data');
            }
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/xelqun-forms.php');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'xelqun-forms');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/xelqun-forms.php' => config_path('xelqun-forms.php'),
            ], 'xelqun-forms-config');

            $this->publishes([
                __DIR__.'/../resources/lang' => lang_path('vendor/xelqun-forms'),
            ], 'xelqun-forms-lang');

            $this->commands([
                InstallCommand::class,
                InstallPluginCommand::class,
                MakeFieldCommand::class,
                MakeFormCommand::class,
                PruneTemporaryUploadsCommand::class,
            ]);
        }
    }
}
