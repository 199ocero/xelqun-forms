<?php

use Illuminate\Support\Facades\Route;
use Xelqun\Forms\Http\Controllers\ActionController;
use Xelqun\Forms\Http\Controllers\SearchController;
use Xelqun\Forms\Http\Controllers\UpdateController;
use Xelqun\Forms\Http\Controllers\UploadController;
use Xelqun\Forms\Http\Controllers\ValidateController;

Route::prefix(config('xelqun-forms.routes.prefix'))
    ->middleware(config('xelqun-forms.routes.middleware'))
    ->name('xelqun.forms.')
    ->group(function (): void {
        Route::post('update', UpdateController::class)
            ->middleware('throttle:'.config('xelqun-forms.routes.throttle.update').',xelqun-forms-update')
            ->name('update');

        Route::post('upload', UploadController::class)
            ->middleware('throttle:'.config('xelqun-forms.routes.throttle.upload').',xelqun-forms-upload')
            ->name('upload');

        Route::post('search', SearchController::class)
            ->middleware('throttle:'.config('xelqun-forms.routes.throttle.search').',xelqun-forms-search')
            ->name('search');

        // The default keeps an app working with a config file published before this route.
        Route::post('validate', ValidateController::class)
            ->middleware('throttle:'.config('xelqun-forms.routes.throttle.validate', '120,1').',xelqun-forms-validate')
            ->name('validate');

        Route::post('action', ActionController::class)
            ->middleware('throttle:'.config('xelqun-forms.routes.throttle.action', '60,1').',xelqun-forms-action')
            ->name('action');
    });
