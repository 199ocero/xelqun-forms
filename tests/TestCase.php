<?php

namespace Xelqun\Forms\Tests;

use Acme\Reviews\ReviewsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Xelqun\Forms\FormsServiceProvider;
use Xelqun\Forms\Testing\InteractsWithForms;

abstract class TestCase extends Orchestra
{
    use InteractsWithForms;

    protected function getPackageProviders($app): array
    {
        return [FormsServiceProvider::class, ReviewsServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id');
            $table->string('name');
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('customer');
            $table->string('type')->default('individual');
            $table->string('company')->nullable();
            $table->foreignId('country_id')->nullable();
            $table->foreignId('region_id')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->nullableMorphs('owner');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('group')->default('public');
        });

        Schema::create('order_tag', function (Blueprint $table): void {
            $table->foreignId('order_id');
            $table->foreignId('tag_id');
            $table->string('added_by')->nullable();
        });

        Schema::create('order_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id');
            $table->string('body');
            $table->string('stamp')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id');
            $table->unsignedInteger('sort')->default(0);
            $table->string('product');
            $table->decimal('qty', 12, 2);
            $table->decimal('price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->string('attachment')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->nullable();
            $table->foreignId('order_item_id')->nullable();
            $table->unsignedTinyInteger('stars');
            $table->timestamps();
        });
    }
}
