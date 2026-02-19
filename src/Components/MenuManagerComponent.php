<?php

declare(strict_types=1);

namespace MoonShine\CustomMenuManager\Components;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Collection;
use MoonShine\UI\Components\MoonShineComponent;

final class MenuManagerComponent extends MoonShineComponent implements Renderable
{
    protected string $view = 'moonshine-menu-manager::menu-manager';

    public function __construct(
        public readonly array $items,
        public readonly Collection $configMap,
        public readonly array $zones,
        public readonly string $saveUrl,
        public readonly array $zoneSettings = [],
    ) {
        parent::__construct();
    }

    protected function viewData(): array
    {
        $zonesLabels = [
            'sidebar' => 'Боковая панель',
            'topbar' => 'Верхняя панель',
            'bottom_bar' => 'Нижняя панель',
        ];

        $configArray = [];
        foreach ($this->configMap as $itemKey => $cfg) {
            $configArray[$itemKey] = [
                'zone' => $cfg->zone ?? 'sidebar',
                'sort_order' => $cfg->sort_order ?? 999,
                'visible' => $cfg->visible !== false && $cfg->visible !== 0,
                'parent_key' => $cfg->parent_key ?? null,
            ];
        }

        return [
            'items' => $this->items,
            'configMap' => $configArray,
            'zones' => $this->zones,
            'saveUrl' => $this->saveUrl,
            'zonesLabels' => $zonesLabels,
            'allZones' => config('moonshine_menu_manager.zones', ['sidebar', 'topbar', 'bottom_bar']),
            'zoneSettings' => $this->zoneSettings,
        ];
    }
}
