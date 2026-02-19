<?php

declare(strict_types=1);

namespace MoonShine\CustomMenuManager\Pages;

use Illuminate\Http\RedirectResponse;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\CustomMenuManager\Components\MenuManagerComponent;
use MoonShine\CustomMenuManager\Services\MenuConfigService;
use MoonShine\CustomMenuManager\Services\MenuDiscoveryService;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Support\Attributes\AsyncMethod;

final class MenuManagerPage extends Page
{
    public function getBreadcrumbs(): array
    {
        return ['#' => $this->getTitle()];
    }

    public function getTitle(): string
    {
        return 'Управление меню';
    }

    /** @return list<ComponentContract> */
    protected function components(): iterable
    {
        $saveUrl = moonshineRouter()->getEndpoints()->method('save', null, [], $this, null);

        $configService = app(MenuConfigService::class);
        $layoutClass = config('moonshine.layout');

        $zoneSettings = [
            'bottom_bar' => [
                'always_visible' => $configService->isBottomBarAlwaysVisible($layoutClass),
            ],
        ];

        return [
            MenuManagerComponent::make(
                app(MenuDiscoveryService::class)->discover(),
                $configService->getConfigMap($layoutClass),
                $configService->getActiveZones($layoutClass),
                $saveUrl,
                $zoneSettings,
            ),
        ];
    }

    #[AsyncMethod]
    public function save(): RedirectResponse|array
    {
        $itemsRaw = request()->input('items', '[]');
        $items = is_string($itemsRaw) ? json_decode($itemsRaw, true) : $itemsRaw;
        if (! is_array($items)) {
            return ['message' => 'Неверные данные', 'messageType' => 'error'];
        }

        $zoneSettingsRaw = request()->input('zone_settings', '{}');
        $zoneSettings = is_string($zoneSettingsRaw) ? json_decode($zoneSettingsRaw, true) : $zoneSettingsRaw;

        try {
            $configService = app(MenuConfigService::class);
            $layoutClass = config('moonshine.layout');

            $configService->saveConfig($layoutClass, $items);

            if (is_array($zoneSettings)) {
                $normalized = [];
                foreach ($zoneSettings as $zone => $settings) {
                    if (is_array($settings)) {
                        $normalized[$zone] = $settings;
                    }
                }
                $configService->saveZoneSettings($normalized, $layoutClass);
            }

            session()->put('toast', ['type' => 'success', 'message' => 'Меню сохранено']);

            return redirect()->back();
        } catch (\Throwable $e) {
            report($e);

            return ['message' => $e->getMessage(), 'messageType' => 'error'];
        }
    }
}
