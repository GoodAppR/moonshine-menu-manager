<?php

declare(strict_types=1);

namespace MoonShine\CustomMenuManager\Services;

use Illuminate\Support\Collection;
use MoonShine\Contracts\MenuManager\MenuElementsContract;
use MoonShine\Contracts\MenuManager\MenuManagerContract;
use MoonShine\CustomMenuManager\Models\MenuZoneSetting;
use MoonShine\CustomMenuManager\Models\MenuItemConfig;
use MoonShine\Laravel\MoonShineAuth;
use MoonShine\MenuManager\MenuElements;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;

final class MenuConfigService
{
    public function __construct(
        private readonly MenuManagerContract $menuManager,
        private readonly MenuDiscoveryService $discovery
    ) {}

    private function resolveUserId(): ?int
    {
        if (! config('moonshine_menu_manager.per_user', false)) {
            return null;
        }
        $user = MoonShineAuth::getGuard()->user();

        return $user?->getKey();
    }

    public function getItemsForZone(string $zone, ?string $layoutClass = null): MenuElementsContract
    {
        $layoutClass = $layoutClass ?? config('moonshine.layout');
        $configMap = $this->getConfigMap($layoutClass, $this->resolveUserId());

        if ($configMap->isEmpty()) {
            if ($zone !== 'sidebar') {
                return MenuElements::make([])->onlyVisible();
            }
            return $this->applyZoneOnly($this->menuManager->all(null), $zone);
        }

        return $this->buildMenuFromConfig($zone, $configMap);
    }

    /** @param  Collection<string, object{zone: string, sort_order: int, visible: bool, parent_key: string|null}>  $configMap */
    private function buildMenuFromConfig(string $zone, Collection $configMap): MenuElementsContract
    {
        $itemElements = $this->discovery->collectItemElements();
        $groupMeta = $this->discovery->collectGroupMeta();

        $itemsInZone = $configMap
            ->filter(fn (object $c) => $c->zone === $zone && $c->visible)
            ->sortBy('sort_order');

        $orderedItems = [];
        foreach ($itemsInZone as $itemKey => $cfg) {
            $element = $itemElements[$itemKey] ?? null;
            if ($element instanceof MenuItem) {
                $orderedItems[] = [
                    'element' => $element,
                    'parent_key' => $cfg->parent_key ?? null,
                    'sort_order' => $cfg->sort_order ?? 999,
                ];
            }
        }

        $blocks = $this->buildBlocksFromOrderedItems($orderedItems);

        $result = [];
        foreach ($blocks as $block) {
            if ($block['parent_key'] === null) {
                foreach ($block['elements'] as $el) {
                    $result[] = $el;
                }
            } else {
                $meta = $groupMeta[$block['parent_key']] ?? null;
                $label = $meta['label'] ?? $block['parent_key'];
                $icon = $meta['icon'] ?? null;
                $result[] = MenuGroup::make($label, $block['elements'], $icon);
            }
        }

        $elements = MenuElements::make($result);

        if ($zone === 'topbar' || $zone === 'bottom_bar') {
            $elements = $elements->topMode();
        }

        return $elements->onlyVisible();
    }

    /** @param  array<int, array{element: MenuItem, parent_key: string|null, sort_order: int}>  $orderedItems
     * @return array<int, array{parent_key: string|null, elements: array<MenuItem>}>
     */
    private function buildBlocksFromOrderedItems(array $orderedItems): array
    {
        $byParent = [];
        foreach ($orderedItems as $row) {
            $parentKey = $row['parent_key'];
            $key = $parentKey === null ? '_standalone_' . spl_object_hash($row['element']) : $parentKey;
            if (! isset($byParent[$key])) {
                $byParent[$key] = ['parent_key' => $parentKey, 'items' => [], 'min_order' => $row['sort_order']];
            }
            $byParent[$key]['items'][] = $row;
            $byParent[$key]['min_order'] = min($byParent[$key]['min_order'], $row['sort_order']);
        }

        foreach ($byParent as &$group) {
            usort($group['items'], fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);
        }
        unset($group);

        uasort($byParent, fn ($a, $b) => $a['min_order'] <=> $b['min_order']);

        $blocks = [];
        foreach ($byParent as $group) {
            $elements = array_column($group['items'], 'element');
            if ($group['parent_key'] === null) {
                foreach ($elements as $el) {
                    $blocks[] = ['parent_key' => null, 'elements' => [$el]];
                }
            } else {
                $blocks[] = [
                    'parent_key' => $group['parent_key'],
                    'elements' => $elements,
                ];
            }
        }

        return $blocks;
    }

    public function getActiveZones(?string $layoutClass = null, ?int $moonshineUserId = null): array
    {
        $layoutClass = $layoutClass ?? config('moonshine.layout');
        $moonshineUserId ??= $this->resolveUserId();
        $defaults = config('moonshine_menu_manager.default_layout_zones', ['sidebar', 'bottom_bar']);
        $allZones = config('moonshine_menu_manager.zones', ['sidebar', 'topbar', 'bottom_bar']);

        $active = array_unique(array_merge(
            $defaults,
            array_filter($allZones, fn (string $z): bool => $this->hasItemsInZone($z, $layoutClass, $moonshineUserId))
        ));

        return array_values(array_intersect($allZones, $active));
    }

    public function hasItemsInZone(string $zone, ?string $layoutClass = null, ?int $moonshineUserId = null): bool
    {
        $layoutClass ??= config('moonshine.layout');
        $moonshineUserId ??= $this->resolveUserId();

        $configMap = $this->getConfigMap($layoutClass, $moonshineUserId);

        return $configMap
            ->filter(fn (object $c) => $c->zone === $zone && $c->visible)
            ->isNotEmpty();
    }

    /** @return Collection<string, object{zone: string, sort_order: int, visible: bool, parent_key: string|null}> */
    public function getConfigMap(string $layoutClass, ?int $moonshineUserId = null): Collection
    {
        $moonshineUserId ??= $this->resolveUserId();

        $query = MenuItemConfig::query()->where('layout_class', $layoutClass);

        if ($moonshineUserId === null) {
            $query->whereNull('moonshine_user_id');
        } else {
            $query->where('moonshine_user_id', $moonshineUserId);
        }

        return $query->get()
            ->keyBy('item_key')
            ->map(fn (MenuItemConfig $c) => (object) [
                'zone' => $c->zone,
                'sort_order' => $c->sort_order,
                'visible' => $c->visible,
                'parent_key' => $c->parent_key ?? null,
            ]);
    }

    public function saveConfig(string $layoutClass, array $items, ?int $moonshineUserId = null): void
    {
        $moonshineUserId ??= $this->resolveUserId();

        $query = MenuItemConfig::query()->where('layout_class', $layoutClass);
        if ($moonshineUserId === null) {
            $query->whereNull('moonshine_user_id');
        } else {
            $query->where('moonshine_user_id', $moonshineUserId);
        }
        $query->delete();

        $order = 0;
        foreach ($items as $item) {
            MenuItemConfig::create([
                'layout_class' => $layoutClass,
                'moonshine_user_id' => $moonshineUserId,
                'item_key' => $item['key'],
                'parent_key' => $item['parent_key'] ?? null,
                'zone' => $item['zone'] ?? 'sidebar',
                'sort_order' => $item['sort_order'] ?? $order++,
                'visible' => $item['visible'] ?? true,
            ]);
        }
    }

    private function applyZoneOnly(MenuElementsContract $items, string $zone): MenuElementsContract
    {
        if ($zone === 'topbar') {
            return $items->topMode()->onlyVisible();
        }

        return $items->onlyVisible();
    }

    public function getZoneSetting(string $zone, string $key, ?string $layoutClass = null, ?int $moonshineUserId = null): ?string
    {
        $layoutClass ??= config('moonshine.layout');
        $moonshineUserId ??= $this->resolveUserId();

        $record = MenuZoneSetting::query()
            ->where('layout_class', $layoutClass)
            ->where('zone', $zone)
            ->where('key', $key)
            ->when($moonshineUserId === null, fn ($q) => $q->whereNull('moonshine_user_id'))
            ->when($moonshineUserId !== null, fn ($q) => $q->where('moonshine_user_id', $moonshineUserId))
            ->first();

        return $record?->value;
    }

    public function isBottomBarAlwaysVisible(?string $layoutClass = null, ?int $moonshineUserId = null): bool
    {
        $value = $this->getZoneSetting('bottom_bar', 'always_visible', $layoutClass, $moonshineUserId);

        return $value === '1' || $value === 'true';
    }

    public function saveZoneSettings(array $settings, ?string $layoutClass = null, ?int $moonshineUserId = null): void
    {
        $layoutClass ??= config('moonshine.layout');
        $moonshineUserId ??= $this->resolveUserId();

        foreach ($settings as $zone => $zoneSettings) {
            if (! is_array($zoneSettings)) {
                continue;
            }
            foreach ($zoneSettings as $key => $value) {
                MenuZoneSetting::updateOrCreate(
                    [
                        'layout_class' => $layoutClass,
                        'moonshine_user_id' => $moonshineUserId,
                        'zone' => $zone,
                        'key' => $key,
                    ],
                    ['value' => $value ? '1' : '0']
                );
            }
        }
    }
}
