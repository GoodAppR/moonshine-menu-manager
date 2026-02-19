<?php

declare(strict_types=1);

namespace MoonShine\CustomMenuManager\Services;

use Illuminate\Support\Str;
use MoonShine\Contracts\MenuManager\MenuElementContract;
use MoonShine\Contracts\MenuManager\MenuFillerContract;
use MoonShine\Contracts\MenuManager\MenuManagerContract;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;

final class MenuDiscoveryService
{
    public function __construct(
        private readonly MenuManagerContract $menuManager
    ) {}

    /** @return array<int, array{key: string, label: string, icon: string|null, parent_key: string|null, type: string}> */
    public function discover(?string $layoutClass = null): array
    {
        $items = $this->menuManager->all(null);
        $flat = $this->flattenElements($items, null);
        foreach ($flat as $idx => $row) {
            $flat[$idx]['sort_index'] = $idx;
        }

        return $flat;
    }

    /** @return array<string, MenuItem> */
    public function collectItemElements(): array
    {
        $items = $this->menuManager->all(null);
        $map = [];

        $this->collectElementsRecursive($items, $map);

        return $map;
    }

    /** @return array<string, array{label: string, icon: string|null}> */
    public function collectGroupMeta(): array
    {
        $items = $this->menuManager->all(null);
        $meta = [];

        $this->collectGroupMetaRecursive($items, $meta);

        return $meta;
    }

    /** @param  iterable<MenuElementContract>  $elements
     * @param  array<string, MenuItem>  $map
     */
    private function collectElementsRecursive(iterable $elements, array &$map): void
    {
        foreach ($elements as $element) {
            if (! $element->isSee()) {
                continue;
            }
            if ($element instanceof MenuItem) {
                $key = $this->resolveKey($element);
                $map[$key] = $element;
            }
            if ($element instanceof MenuGroup) {
                $this->collectElementsRecursive($element->getItems(), $map);
            }
        }
    }

    /** @param  iterable<MenuElementContract>  $elements
     * @param  array<string, array{label: string, icon: string|null}>  $meta
     */
    private function collectGroupMetaRecursive(iterable $elements, array &$meta): void
    {
        foreach ($elements as $element) {
            if (! $element->isSee()) {
                continue;
            }
            if ($element instanceof MenuGroup) {
                $key = $this->resolveKey($element);
                $label = $element->getLabel();
                $label = $label instanceof \Closure ? value($label) : (string) $label;
                $meta[$key] = ['label' => $label, 'icon' => $element->getIconValue()];
                $this->collectGroupMetaRecursive($element->getItems(), $meta);
            }
        }
    }

    /** @param  iterable<MenuElementContract>  $elements
     * @return array<int, array{key: string, label: string, icon: string|null, parent_key: string|null, type: string}>
     */
    private function flattenElements(iterable $elements, ?string $parentKey): array
    {
        $result = [];

        foreach ($elements as $element) {
            if (! $element->isSee()) {
                continue;
            }

            $key = $this->resolveKey($element);
            $label = $element->getLabel();
            if ($label instanceof \Closure) {
                $label = value($label);
            }
            $label = is_string($label) ? $label : '';

            $result[] = [
                'key' => $key,
                'label' => $label,
                'icon' => $element->getIconValue(),
                'parent_key' => $parentKey,
                'type' => $element instanceof MenuGroup ? 'group' : 'item',
            ];

            if ($element instanceof MenuGroup) {
                $children = $this->flattenElements($element->getItems(), $key);
                $result = array_merge($result, $children);
            }
        }

        return $result;
    }

    public function resolveKey(MenuElementContract $element): string
    {
        if ($element instanceof MenuItem) {
            $filler = $element->getFiller();
            if ($filler instanceof MenuFillerContract) {
                return $filler::class;
            }
            if (is_string($filler) && str_contains($filler, '\\')) {
                return $filler;
            }
        }

        if ($element instanceof MenuGroup) {
            $label = $element->getLabel();
            $label = $label instanceof \Closure ? value($label) : (string) $label;

            return 'group:' . Str::slug($label) . ':' . md5($label);
        }

        return 'unknown:' . spl_object_hash($element);
    }
}
