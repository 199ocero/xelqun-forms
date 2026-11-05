<?php

namespace Acme\Reviews;

use Illuminate\Support\ServiceProvider;

/**
 * A plugin needs no plugin class: its service provider is all Laravel has to find.
 */
class ReviewsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'acme-reviews');
    }
}
