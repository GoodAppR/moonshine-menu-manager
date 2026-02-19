@props([
    'items' => [],
    'configMap' => [],
    'zones' => ['sidebar', 'topbar', 'bottom_bar'],
    'saveUrl' => '',
    'zonesLabels' => [],
    'allZones' => ['sidebar', 'topbar', 'bottom_bar'],
    'zoneSettings' => [],
])

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.7/Sortable.min.js"></script>

<div
    x-data="menuManager({
        items: @js($items),
        configMap: @js($configMap),
        zones: @js($zones),
        allZones: @js($allZones),
        zonesLabels: @js($zonesLabels ?? []),
        saveUrl: @js($saveUrl),
        zoneSettings: @js($zoneSettings ?? [])
    })"
    @click.outside="closeGroupZoneDropdown()"
    @init-sortable.window="scheduleSortableInit()"
    class="menu-manager-page"
>
    {{-- ── Header ────────────────────────────────────────────── --}}
    <div class="box space-elements mb-6 rounded-2xl overflow-hidden border border-secondary-200 dark:border-secondary-700">
        <h2 class="box-title text-lg font-semibold text-secondary-800 dark:text-secondary-200">
            Как настроить меню
        </h2>
        <p class="text-sm text-secondary-600 dark:text-secondary-400 mb-4">
            Перетаскивание за ↕ в своей зоне. Стрелка — свернуть/развернуть.
            Выпадающий список — зона. Глаз — скрыть/показать.
            Скрытые — клик возвращает в меню.
        </p>
        <button
            type="button"
            @click="save()"
            :disabled="saving"
            class="btn btn-primary w-full sm:w-auto"
        >
            <span x-show="!saving">Сохранить</span>
            <span x-show="saving">Сохранение...</span>
        </button>
    </div>

    {{-- ── Zone Columns ──────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @foreach($allZones as $zone)
            @php
                $zoneInfo = [
                    'sidebar'    => ['icon' => 'bars-4',               'desc' => 'Слева'],
                    'topbar'     => ['icon' => 'squares-2x2',         'desc' => 'Сверху'],
                    'bottom_bar' => ['icon' => 'arrow-down-on-square', 'desc' => 'Внизу'],
                ];
                $zoneData = $zoneInfo[$zone] ?? ['icon' => 'queue-list', 'desc' => ''];
            @endphp

            <div
                x-show="isZoneVisible('{{ $zone }}')"
                x-transition
                class="box space-elements rounded-2xl overflow-hidden border border-secondary-200 dark:border-secondary-700 flex flex-col"
            >
                {{-- Zone header --}}
                <h2 class="box-title px-4 py-3 bg-secondary-100 dark:bg-dark-700 border-b border-secondary-200 dark:border-secondary-600 flex items-center justify-between gap-2 flex-wrap">
                    <span class="flex items-center gap-2">
                        <x-moonshine::icon :icon="$zoneData['icon']" class="w-5 h-5 text-secondary-600 dark:text-secondary-400" />
                        <span class="font-semibold text-secondary-800 dark:text-secondary-200">{{ $zonesLabels[$zone] ?? $zone }}</span>
                        <span class="text-xs text-secondary-500">{{ $zoneData['desc'] }}</span>
                    </span>

                    @if($zone === 'bottom_bar')
                        <label class="flex items-center gap-2 text-xs text-secondary-600 dark:text-secondary-400 cursor-pointer shrink-0">
                            <input type="checkbox" x-model="zoneSettings.bottom_bar.always_visible" class="toggle toggle-sm toggle-primary" />
                            <span>Показывать всегда</span>
                        </label>
                    @endif

                    <span class="text-xs text-secondary-500" x-text="'Пунктов: ' + getItemsInZone('{{ $zone }}').length"></span>
                </h2>

                {{-- Zone sortable list --}}
                <ul
                    x-ref="sortable-{{ $zone }}"
                    data-zone="{{ $zone }}"
                    class="min-h-[100px] max-h-[400px] overflow-y-auto p-2 space-y-1 flex-1 zone-list"
                >
                    <template x-for="block in getBlocksInZone('{{ $zone }}')" :key="block.type === 'group' ? 'g-'+block.key : 's-'+block.item.key">
                        <li
                            class="rounded-lg border border-transparent hover:border-secondary-200 dark:hover:border-dark-500 overflow-hidden"
                            :class="block.type === 'group' ? 'bg-secondary-50/50 dark:bg-dark-800/30' : ''"
                            :data-block-id="block.type === 'group' ? 'g-'+block.key : 's-'+block.item.key"
                            :data-group-key="block.type === 'group' ? block.key : null"
                            :data-item-key="block.type === 'standalone' ? block.item.key : null"
                        >
                            {{-- ── Group block ── --}}
                            <template x-if="block.type === 'group'">
                                <div>
                                    {{-- Group header --}}
                                    <div class="flex items-center gap-2 py-2 px-3 text-xs font-semibold text-secondary-500 uppercase tracking-wider border-b border-secondary-200 dark:border-dark-600">
                                        <span class="sortable-drag-handle shrink-0 p-1 rounded hover:bg-secondary-200 dark:hover:bg-dark-600 cursor-grab active:cursor-grabbing" title="Перетащить">
                                            <x-moonshine::icon icon="arrows-up-down" class="w-4 h-4 text-secondary-400" />
                                        </span>
                                        <button
                                            type="button"
                                            @click.stop="toggleGroupCollapse(block.key)"
                                            @mousedown.stop
                                            class="shrink-0 p-0.5 rounded hover:bg-secondary-200 dark:hover:bg-dark-600 transition-transform"
                                            :class="block.collapsed ? '' : 'rotate-90'"
                                            title="Свернуть/развернуть"
                                        >
                                            <x-moonshine::icon icon="chevron-right" class="w-4 h-4" />
                                        </button>
                                        <x-moonshine::icon icon="folder" class="w-4 h-4 shrink-0" />
                                        <span class="flex-1 min-w-0 text-xs break-words" x-text="block.label"></span>
                                        <span class="menu-item-dots text-secondary-500" aria-hidden="true"></span>

                                        <button
                                            type="button"
                                            @click.stop.prevent="openGroupZoneDropdown(block.key, $event)"
                                            @mousedown.stop
                                            class="select select-bordered select-xs w-28 text-xs py-1 flex items-center justify-between gap-1 text-left shrink-0"
                                            title="Зона группы"
                                        >
                                            <span x-text="getZoneLabel(block.zone)" class="truncate"></span>
                                            <x-moonshine::icon icon="chevron-down" class="w-3 h-3 shrink-0 opacity-60" />
                                        </button>

                                        <button
                                            type="button"
                                            @click.stop.prevent="setGroupVisible(block.key, !isGroupVisible(block.key))"
                                            @mousedown.stop
                                            class="shrink-0 p-1 rounded hover:bg-secondary-200 dark:hover:bg-dark-600"
                                            :title="isGroupVisible(block.key) ? 'Скрыть группу' : 'Показать группу'"
                                        >
                                            <x-moonshine::icon icon="eye-slash" class="w-4 h-4" x-show="isGroupVisible(block.key)" />
                                            <x-moonshine::icon icon="eye" class="w-4 h-4 text-secondary-400" x-show="!isGroupVisible(block.key)" />
                                        </button>
                                    </div>

                                    {{-- Group items — rendered directly inside the sortable container --}}
                                    <template x-if="!block.collapsed">
                                        <ul
                                            class="group-items-sortable pl-6 pr-2 py-1 ml-3 border-l-2 border-secondary-200 dark:border-dark-600"
                                            data-group-items-container
                                            :data-group-key="block.key"
                                            :data-zone="block.zone"
                                            x-init="$nextTick(() => $dispatch('init-sortable'))"
                                        >
                                            <template x-for="item in block.items" :key="item.key">
                                                <li
                                                    class="flex items-center gap-2 py-2 px-3 rounded-lg hover:bg-secondary-100 dark:hover:bg-dark-600"
                                                    :data-item-key="item.key"
                                                >
                                                    <span class="group-item-drag-handle shrink-0 p-1 rounded hover:bg-secondary-200 dark:hover:bg-dark-600 cursor-grab active:cursor-grabbing" title="Перетащить">
                                                        <x-moonshine::icon icon="arrows-up-down" class="w-4 h-4 text-secondary-400" />
                                                    </span>
                                                    <span class="flex-1 min-w-0 text-sm break-words" x-text="item.label"></span>
                                                    <span class="menu-item-dots text-secondary-500 shrink-0" aria-hidden="true"></span>
                                                    <span class="flex items-center gap-2 shrink-0">
                                                        <select
                                                            :value="item.parent_key || ''"
                                                            @change="updateItemParent(item.key, $event.target.value)"
                                                            @mousedown.stop
                                                            class="select select-bordered select-xs w-32 text-xs py-1"
                                                            title="Группа"
                                                        >
                                                            <option value="">— без группы</option>
                                                            <template x-for="gk in getAllGroupKeys()" :key="'gp-'+gk">
                                                                <option :value="gk" x-text="getGroupLabel(gk)" :selected="(item.parent_key || '') === gk"></option>
                                                            </template>
                                                        </select>
                                                        <select
                                                            :value="item.zone"
                                                            @change="updateItemZone(item.key, $event.target.value)"
                                                            @mousedown.stop
                                                            class="select select-bordered select-xs w-28 text-xs py-1"
                                                        >
                                                            @foreach($allZones as $z)
                                                                <option value="{{ $z }}">{{ $zonesLabels[$z] ?? $z }}</option>
                                                            @endforeach
                                                        </select>
                                                        <button
                                                            type="button"
                                                            @click.stop="toggleItemVisible(item.key)"
                                                            @mousedown.stop
                                                            class="shrink-0 p-1 rounded hover:bg-secondary-200 dark:hover:bg-dark-600"
                                                            :title="item.visible ? 'Скрыть' : 'Показать'"
                                                        >
                                                            <x-moonshine::icon icon="eye-slash" class="w-4 h-4" x-show="item.visible" />
                                                            <x-moonshine::icon icon="eye" class="w-4 h-4 text-secondary-400" x-show="!item.visible" />
                                                        </button>
                                                    </span>
                                                </li>
                                            </template>
                                        </ul>
                                    </template>
                                </div>
                            </template>

                            {{-- ── Standalone block ── --}}
                            <template x-if="block.type === 'standalone'">
                                <div class="flex items-center gap-2 py-2 px-3 rounded-lg hover:bg-secondary-100 dark:hover:bg-dark-600">
                                    <span class="sortable-drag-handle shrink-0 p-1 rounded hover:bg-secondary-200 dark:hover:bg-dark-600 cursor-grab active:cursor-grabbing" title="Перетащить">
                                        <x-moonshine::icon icon="arrows-up-down" class="w-4 h-4 text-secondary-400" />
                                    </span>
                                    <span class="flex-1 min-w-0 text-sm break-words" x-text="block.item.label"></span>
                                    <span class="menu-item-dots text-secondary-500" aria-hidden="true"></span>
                                    <span class="flex items-center gap-2 shrink-0">
                                        <select
                                            :value="block.item.parent_key || ''"
                                            @change="updateItemParent(block.item.key, $event.target.value)"
                                            @mousedown.stop
                                            class="select select-bordered select-xs w-32 text-xs py-1"
                                            title="Группа"
                                        >
                                            <option value="">— без группы</option>
                                            <template x-for="gk in getAllGroupKeys()" :key="'gp-'+gk">
                                                <option :value="gk" x-text="getGroupLabel(gk)" :selected="(block.item.parent_key || '') === gk"></option>
                                            </template>
                                        </select>
                                        <select
                                            :value="block.item.zone"
                                            @change="updateItemZone(block.item.key, $event.target.value)"
                                            @mousedown.stop
                                            class="select select-bordered select-xs w-28 text-xs py-1"
                                        >
                                            @foreach($allZones as $z)
                                                <option value="{{ $z }}">{{ $zonesLabels[$z] ?? $z }}</option>
                                            @endforeach
                                        </select>
                                        <button
                                            type="button"
                                            @click.stop="toggleItemVisible(block.item.key)"
                                            @mousedown.stop
                                            class="shrink-0 p-1 rounded hover:bg-secondary-200 dark:hover:bg-dark-600"
                                            :title="block.item.visible ? 'Скрыть' : 'Показать'"
                                        >
                                            <x-moonshine::icon icon="eye-slash" class="w-4 h-4" x-show="block.item.visible" />
                                            <x-moonshine::icon icon="eye" class="w-4 h-4 text-secondary-400" x-show="!block.item.visible" />
                                        </button>
                                    </span>
                                </div>
                            </template>
                        </li>
                    </template>

                    {{-- Empty zone placeholder --}}
                    <li
                        x-show="getItemsInZone('{{ $zone }}').length === 0"
                        class="py-6 text-center text-secondary-400 text-sm italic"
                    >
                        Нет пунктов. Выберите «{{ $zonesLabels[$zone] ?? $zone }}» в других.
                    </li>
                </ul>
            </div>
        @endforeach
    </div>

    {{-- ── Hidden Items ──────────────────────────────────────── --}}
    <div class="box space-elements mt-6 rounded-2xl overflow-hidden border border-secondary-200 dark:border-secondary-700">
        <h2 class="box-title font-medium text-secondary-700 dark:text-secondary-300 mb-3 flex items-center gap-2">
            <x-moonshine::icon icon="eye-slash" class="w-4 h-4" />
            Скрытые
            <span class="text-xs font-normal text-secondary-500">(клик — вернуть в меню)</span>
        </h2>

        <div class="flex flex-wrap gap-2" x-show="getHiddenGroups().length > 0 || getHiddenStandaloneItems().length > 0">
            <template x-for="gk in getHiddenGroups()" :key="'hg-'+gk">
                <button
                    type="button"
                    @click="setGroupVisible(gk, true)"
                    class="btn btn-sm btn-ghost inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-secondary-100 dark:bg-dark-700 text-secondary-600 hover:bg-secondary-200 dark:hover:bg-dark-600 text-sm"
                >
                    <x-moonshine::icon icon="eye" class="w-4 h-4" />
                    <span x-text="getGroupLabel(gk)"></span>
                    <span class="text-xs opacity-70">(группа)</span>
                </button>
            </template>

            <template x-for="item in getHiddenStandaloneItems()" :key="'hi-'+item.key">
                <button
                    type="button"
                    @click="toggleItemVisible(item.key)"
                    class="btn btn-sm btn-ghost inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-secondary-100 dark:bg-dark-700 text-secondary-600 hover:bg-secondary-200 dark:hover:bg-dark-600 text-sm"
                >
                    <x-moonshine::icon icon="eye" class="w-4 h-4" />
                    <span x-text="item.label"></span>
                </button>
            </template>
        </div>

        <p
            x-show="getHiddenGroups().length === 0 && getHiddenStandaloneItems().length === 0"
            class="text-secondary-400 text-sm"
        >
            Нет скрытых
        </p>
    </div>

    {{-- ── Group Zone Dropdown (floating) ────────────────────── --}}
    <div
        x-show="openGroupZone"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        :style="groupZoneDropdownStyle"
        class="fixed z-[9999] rounded-lg border border-secondary-200 dark:border-secondary-700 bg-white dark:bg-dark-800 shadow-lg py-1 overflow-hidden"
    >
        <template x-for="z in allZones" :key="z">
            <button
                type="button"
                @click="selectGroupZone(z)"
                class="block w-full text-left px-3 py-2 text-sm hover:bg-secondary-100 dark:hover:bg-dark-600 transition-colors"
            >
                <span x-text="getZoneLabel(z)"></span>
            </button>
        </template>
    </div>
</div>
