<?php

declare(strict_types=1);

namespace MoonShine\CustomMenuManager\Providers;

use Illuminate\Support\ServiceProvider;
use MoonShine\AssetManager\InlineCss;
use MoonShine\AssetManager\InlineJs;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\CustomMenuManager\Pages\MenuManagerPage;
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

        // Register page in moonshine.pages (for config and PageController lookup)
        $pages = config('moonshine.pages', []);
        $pages['menu-manager'] = MenuManagerPage::class;
        config(['moonshine.pages' => $pages]);

        // Add page to Core when it's first resolved (before App's MoonShineServiceProvider uses it)
        $this->app->resolving(CoreContract::class, static function (CoreContract $core): void {
            $core->pages([MenuManagerPage::class]);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'moonshine-menu-manager');

        $this->publishes([
            __DIR__ . '/../../config/moonshine_menu_manager.php' => config_path('moonshine_menu_manager.php'),
        ], 'moonshine-menu-manager-config');

        $this->app->booted(function (): void {
            // Fallback: ensure page is in Core (fixes 404 when config was cached or load order differs)
            if (function_exists('moonshine') && ! moonshine()->getPages()->findByUri('menu-manager-page')) {
                moonshine()->pages([MenuManagerPage::class]);
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
