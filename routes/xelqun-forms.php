<?php

use Illuminate\Support\Facades\Route;
use Xelqun\Forms\Http\Controllers\SearchController;
use Xelqun\Forms\Http\Controllers\UpdateController;
use Xelqun\Forms\Http\Controllers\UploadController;

Route::prefix(config('xelqun-forms.routes.prefix'))
    ->middleware(config('xelqun-forms.routes.middleware'))
    ->name('xelqun.forms.')
    ->group(function (): void {
        Route::post('update', UpdateController::class)
            ->middleware('throttle:'.config('xelqun-forms.routes.throttle.update'))
            ->name('update');

        Route::post('upload', UploadController::class)
            ->middleware('throttle:'.config('xelqun-forms.routes.throttle.upload'))
            ->name('upload');

        Route::post('search', SearchController::class)
            ->middleware('throttle:'.config('xelqun-forms.routes.throttle.search'))
            ->name('search');
    });
