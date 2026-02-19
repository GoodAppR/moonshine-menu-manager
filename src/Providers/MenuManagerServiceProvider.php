<?php

declare(strict_types=1);

namespace MoonShine\CustomMenuManager\Providers;

use Illuminate\Support\ServiceProvider;
use MoonShine\AssetManager\InlineCss;
use MoonShine\AssetManager\InlineJs;
use MoonShine\CustomMenuManager\Services\MenuConfigService;
use MoonShine\CustomMenuManager\Services\MenuDiscoveryService;

final class MenuManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MenuConfigService::class);
        $this->app->singleton(MenuDiscoveryService::class);

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/moonshine_menu_manager.php',
            'moonshine_menu_manager'
        );

        // Register page in moonshine.pages for config (before MoonShine Core boots)
        $pages = config('moonshine.pages', []);
        $pages['menu-manager'] = \MoonShine\CustomMenuManager\Pages\MenuManagerPage::class;
        config(['moonshine.pages' => $pages]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'moonshine-menu-manager');

        $this->publishes([
            __DIR__ . '/../../config/moonshine_menu_manager.php' => config_path('moonshine_menu_manager.php'),
        ], 'moonshine-menu-manager-config');

        $this->app->booted(function (): void {
            if (function_exists('moonshine')) {
                moonshine()->pages([\MoonShine\CustomMenuManager\Pages\MenuManagerPage::class]);
            }
            if (function_exists('moonshineAssets')) {
                $cssPath = __DIR__ . '/../../resources/css/menu-manager.css';
                if (is_file($cssPath)) {
                    moonshineAssets()->prepend(InlineCss::make(file_get_contents($cssPath)));
                }
                $scriptPath = __DIR__ . '/../../resources/js/menu-manager.js';
                if (is_file($scriptPath)) {
                    moonshineAssets()->prepend(InlineJs::make(file_get_contents($scriptPath)));
                }
            }
        });
    }
}
