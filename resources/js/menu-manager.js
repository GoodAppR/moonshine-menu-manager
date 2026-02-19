document.addEventListener('alpine:init', () => {
    Alpine.data('menuManager', (config) => ({
        items: [],
        configMap: {},
        zones: [],
        allZones: [],
        zonesLabels: {},
        saveUrl: '',
        saving: false,
        collapsedGroups: {},
        zoneSettings: {},

        _sortableInstances: [],
        _sortableTimer: null,
        _dragInProgress: false,

        openGroupZone: null,
        groupZoneDropdownStyle: '',

        // ── Lifecycle ──────────────────────────────────────────────

        init() {
            this.configMap = config.configMap || {};
            this.zones = config.zones || ['sidebar', 'topbar', 'bottom_bar'];
            this.allZones = config.allZones || this.zones;
            this.zonesLabels = config.zonesLabels || {};
            this.saveUrl = config.saveUrl || '';

            this.zoneSettings = {
                bottom_bar: {
                    always_visible: config.zoneSettings?.bottom_bar?.always_visible ?? false,
                },
            };

            this._loadItems(config.items || []);
            this.$nextTick(() => this.scheduleSortableInit());
        },

        _loadItems(rawItems) {
            this.items = rawItems.map((item, index) => {
                const cfg = this.configMap[item.key];
                const hasConfig = !!cfg;
                const defaultOrder = (item.sort_index ?? index) * 10;

                return {
                    ...item,
                    zone: cfg?.zone ?? 'sidebar',
                    visible: hasConfig ? (cfg.visible !== false && cfg.visible !== 0) : true,
                    sort_order: hasConfig && cfg.sort_order !== undefined
                        ? cfg.sort_order
                        : defaultOrder,
                    parent_key: hasConfig ? (cfg.parent_key ?? null) : (item.parent_key ?? null),
                };
            });

            const groupKeys = [...new Set(
                this.items.filter(i => i.type === 'group').map(i => i.key),
            )];
            this.collapsedGroups = Object.fromEntries(groupKeys.map(k => [k, true]));
        },

        // ── Zone helpers ───────────────────────────────────────────

        getZoneLabel(zone) {
            return this.zonesLabels[zone] || zone || '';
        },

        isZoneVisible(zone) {
            if (this.zones.includes(zone)) return true;
            return this.items.some(i => i.zone === zone && i.visible && i.type !== 'group');
        },

        getItemsInZone(zone) {
            return this.items
                .filter(i => i.zone === zone && i.visible && i.type !== 'group')
                .sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
        },

        getBlocksInZone(zone) {
            const zoneItems = this.items.filter(
                i => i.zone === zone && i.visible && i.type !== 'group',
            );

            const grouped = new Map();
            const standalone = [];

            for (const item of zoneItems) {
                if (item.parent_key) {
                    if (!grouped.has(item.parent_key)) grouped.set(item.parent_key, []);
                    grouped.get(item.parent_key).push(item);
                } else {
                    standalone.push(item);
                }
            }

            grouped.forEach(items => {
                items.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
            });

            const blocks = [];

            grouped.forEach((items, groupKey) => {
                const group = this.items.find(i => i.key === groupKey && i.type === 'group');
                if (!group) return;
                blocks.push({
                    type: 'group',
                    key: groupKey,
                    label: group.label,
                    zone,
                    collapsed: !!this.collapsedGroups[groupKey],
                    items,
                    sort_order: Math.min(...items.map(i => i.sort_order || 0)),
                });
            });

            for (const item of standalone) {
                blocks.push({ type: 'standalone', item, sort_order: item.sort_order || 0 });
            }

            blocks.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
            return blocks;
        },

        // ── Group helpers ──────────────────────────────────────────

        getAllGroupKeys() {
            return [...new Set(
                this.items.filter(i => i.type === 'group').map(i => i.key),
            )];
        },

        getGroupLabel(groupKey) {
            const g = this.items.find(i => i.key === groupKey && i.type === 'group');
            return g?.label || groupKey;
        },

        getGroupZone(groupKey) {
            const item = this.items.find(
                i => i.type !== 'group' && String(i.parent_key || '') === groupKey,
            );
            return item?.zone || 'sidebar';
        },

        isGroupVisible(groupKey) {
            const children = this.items.filter(
                i => i.type !== 'group' && String(i.parent_key || '') === groupKey,
            );
            return children.length > 0 && children.some(i => i.visible);
        },

        // ── Mutations ──────────────────────────────────────────────

        toggleGroupCollapse(groupKey) {
            this.collapsedGroups[groupKey] = !this.collapsedGroups[groupKey];
            this.collapsedGroups = { ...this.collapsedGroups };
            this.$nextTick(() => this.scheduleSortableInit());
        },

        setGroupVisible(groupKey, visible) {
            this.items
                .filter(i => i.type !== 'group' && String(i.parent_key || '') === groupKey)
                .forEach(i => { i.visible = visible; });
            this.items = [...this.items];
        },

        toggleItemVisible(itemKey) {
            const item = this.items.find(i => i.key === itemKey && i.type !== 'group');
            if (!item) return;
            item.visible = !item.visible;
            this.items = [...this.items];
        },

        updateItemZone(itemKey, zone) {
            const item = this.items.find(i => i.key === itemKey && i.type !== 'group');
            if (!item || !zone) return;
            item.zone = zone;
            item.parent_key = null;
            this.items = [...this.items];
            this.$nextTick(() => this.scheduleSortableInit());
        },

        updateItemParent(itemKey, parentKey) {
            const item = this.items.find(i => i.key === itemKey && i.type !== 'group');
            if (!item) return;

            const newParent = (!parentKey || parentKey === '') ? null : String(parentKey).trim();
            if (newParent === item.parent_key) return;

            item.parent_key = newParent;

            if (newParent) {
                item.zone = this.getGroupZone(newParent);
                const siblings = this.items.filter(
                    i => i.type !== 'group' && String(i.parent_key || '') === newParent,
                );
                item.sort_order = siblings.length;
            } else {
                const siblings = this.items.filter(
                    i => i.zone === item.zone && i.visible && i.type !== 'group' && !i.parent_key,
                );
                item.sort_order = siblings.length;
            }

            this.items = [...this.items];
            this.$nextTick(() => this.scheduleSortableInit());
        },

        moveGroupToZone(groupKey, zone) {
            if (!zone || !groupKey) return;
            this.items
                .filter(i => i.type !== 'group' && String(i.parent_key || '') === groupKey)
                .forEach(i => { i.zone = zone; });
            this.items = [...this.items];
            this.$nextTick(() => this.scheduleSortableInit());
        },

        // ── Drag & Drop ───────────────────────────────────────────

        scheduleSortableInit() {
            clearTimeout(this._sortableTimer);
            this._sortableTimer = setTimeout(() => this._initSortable(), 150);
        },

        _initSortable() {
            if (typeof Sortable === 'undefined') return;

            for (const inst of this._sortableInstances) {
                try { inst.destroy(); } catch (_) { /* noop */ }
            }
            this._sortableInstances = [];

            for (const zone of this.allZones) {
                const el = this.$refs[`sortable-${zone}`];
                if (!el) continue;

                this._sortableInstances.push(
                    Sortable.create(el, {
                        group: 'zone-blocks',
                        animation: 150,
                        ghostClass: 'sortable-ghost',
                        dragClass: 'sortable-drag',
                        handle: '.sortable-drag-handle',
                        draggable: 'li[data-block-id]',
                        swapThreshold: 0.65,
                        onStart: (evt) => {
                            this._dragInProgress = true;
                            this._storeOriginalPosition(evt);
                        },
                        onEnd: (evt) => {
                            this._dragInProgress = false;
                            this._handleZoneBlockDrop(evt);
                        },
                    }),
                );
            }

            const groupContainers = this.$el?.querySelectorAll(
                '[data-group-items-container]',
            ) || [];

            for (const container of groupContainers) {
                const groupKey = container.dataset?.groupKey;
                if (!groupKey) continue;

                this._sortableInstances.push(
                    Sortable.create(container, {
                        group: 'group-items',
                        animation: 150,
                        ghostClass: 'sortable-ghost',
                        dragClass: 'sortable-drag',
                        handle: '.group-item-drag-handle',
                        draggable: 'li[data-item-key]',
                        swapThreshold: 0.65,
                        forceFallback: true,
                        fallbackOnBody: true,
                        onStart: (evt) => {
                            this._dragInProgress = true;
                            this._storeOriginalPosition(evt);
                        },
                        onEnd: (evt) => {
                            this._dragInProgress = false;
                            this._handleGroupItemDrop(evt);
                        },
                    }),
                );
            }
        },

        _storeOriginalPosition(evt) {
            evt.item._origParent = evt.item.parentNode;
            evt.item._origNext = evt.item.nextElementSibling;
        },

        _revertDom(evt) {
            if (evt.item._origParent) {
                evt.item._origParent.insertBefore(evt.item, evt.item._origNext);
            }
        },

        _blockIdsFrom(container) {
            return [...container.children]
                .filter(el => el.tagName === 'LI' && el.dataset?.blockId)
                .map(el => el.dataset.blockId);
        },

        _itemKeysFrom(container) {
            return [...container.children]
                .filter(el => el.tagName === 'LI' && el.dataset?.itemKey)
                .map(el => el.dataset.itemKey);
        },

        _handleZoneBlockDrop(evt) {
            const fromZone = evt.from.dataset.zone;
            const toZone = evt.to.dataset.zone;
            if (!fromZone || !toZone) return;

            const toBlockIds = this._blockIdsFrom(evt.to);
            const fromBlockIds = evt.from !== evt.to
                ? this._blockIdsFrom(evt.from)
                : [];

            this._revertDom(evt);

            const blockId = evt.item.dataset.blockId;
            if (blockId && fromZone !== toZone) {
                const groupKey = evt.item.dataset.groupKey;
                const itemKey = evt.item.dataset.itemKey;

                if (groupKey) {
                    this.items
                        .filter(i => i.type !== 'group' && String(i.parent_key || '') === groupKey)
                        .forEach(i => { i.zone = toZone; });
                } else if (itemKey) {
                    const item = this.items.find(i => i.key === itemKey && i.type !== 'group');
                    if (item) item.zone = toZone;
                }
            }

            this._recalcZoneOrder(toZone, toBlockIds);
            if (evt.from !== evt.to) {
                this._recalcZoneOrder(fromZone, fromBlockIds);
            }

            this.items = [...this.items];
            this.$nextTick(() => this.scheduleSortableInit());
        },

        _handleGroupItemDrop(evt) {
            const itemKey = evt.item.dataset.itemKey;
            const item = this.items.find(i => i.key === itemKey && i.type !== 'group');
            if (!item) return;

            const toGroupKey = evt.to.dataset.groupKey || null;
            const toZone = evt.to.dataset.zone || item.zone;
            const fromGroupKey = evt.from.dataset.groupKey || null;
            const sameGroup = fromGroupKey === toGroupKey;

            const toKeys = this._itemKeysFrom(evt.to);
            const fromKeys = !sameGroup ? this._itemKeysFrom(evt.from) : [];

            this._revertDom(evt);

            if (item.parent_key !== toGroupKey) item.parent_key = toGroupKey;
            if (item.zone !== toZone) item.zone = toZone;

            this._recalcGroupOrder(toGroupKey, toKeys, sameGroup ? null : itemKey);
            if (!sameGroup && fromGroupKey) {
                this._recalcGroupOrder(fromGroupKey, fromKeys);
            }

            this.items = [...this.items];
            this.$nextTick(() => this.scheduleSortableInit());
        },

        _recalcZoneOrder(zone, blockIds) {
            let order = 0;
            for (const id of blockIds) {
                if (id.startsWith('g-')) {
                    const gk = id.substring(2);
                    this.items
                        .filter(i =>
                            i.type !== 'group'
                            && String(i.parent_key || '') === gk
                            && i.zone === zone,
                        )
                        .sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0))
                        .forEach(i => { i.sort_order = order++; });
                } else if (id.startsWith('s-')) {
                    const item = this.items.find(
                        i => i.key === id.substring(2) && i.type !== 'group',
                    );
                    if (item) item.sort_order = order++;
                }
            }
        },

        _recalcGroupOrder(groupKey, itemKeys, excludeKey = null) {
            if (!groupKey) return;

            const existing = this.items.filter(
                i => i.type !== 'group'
                    && String(i.parent_key || '') === groupKey
                    && (!excludeKey || i.key !== excludeKey),
            );
            const base = existing.length > 0
                ? Math.min(...existing.map(i => i.sort_order ?? 0))
                : 0;

            itemKeys.forEach((key, idx) => {
                const item = this.items.find(i => i.key === key && i.type !== 'group');
                if (item) item.sort_order = base + idx;
            });
        },

        // ── Group Zone Dropdown ────────────────────────────────────

        openGroupZoneDropdown(groupKey, ev) {
            if (this.openGroupZone === groupKey) {
                this.closeGroupZoneDropdown();
                return;
            }

            this.openGroupZone = groupKey;
            const btn = ev?.currentTarget;
            if (btn) {
                const r = btn.getBoundingClientRect();
                this.groupZoneDropdownStyle =
                    `top:${r.bottom + 6}px;left:${r.left}px;min-width:${Math.max(r.width, 150)}px;`;
            }
        },

        closeGroupZoneDropdown() {
            this.openGroupZone = null;
            this.groupZoneDropdownStyle = '';
        },

        selectGroupZone(zone) {
            if (this.openGroupZone && zone) {
                this.moveGroupToZone(this.openGroupZone, zone);
            }
            this.closeGroupZoneDropdown();
        },

        // ── Hidden Items ───────────────────────────────────────────

        getHiddenGroups() {
            return this.getAllGroupKeys().filter(gk => {
                const children = this.items.filter(
                    i => i.type !== 'group' && String(i.parent_key || '') === gk,
                );
                return children.length > 0 && children.every(i => !i.visible);
            });
        },

        getHiddenStandaloneItems() {
            const hiddenGK = this.getHiddenGroups();
            return this.items.filter(
                i => !i.visible
                    && i.type !== 'group'
                    && !hiddenGK.includes(i.parent_key || ''),
            );
        },

        // ── Persistence ────────────────────────────────────────────

        _buildPayload() {
            const payload = [];
            const seen = new Set();

            for (const zone of this.allZones) {
                for (const block of this.getBlocksInZone(zone)) {
                    if (block.type === 'group') {
                        for (const item of block.items) {
                            if (seen.has(item.key)) continue;
                            seen.add(item.key);
                            payload.push({
                                key: item.key,
                                parent_key: block.key,
                                zone,
                                sort_order: item.sort_order || 0,
                                visible: item.visible !== false,
                            });
                        }
                    } else {
                        if (seen.has(block.item.key)) continue;
                        seen.add(block.item.key);
                        payload.push({
                            key: block.item.key,
                            parent_key: null,
                            zone,
                            sort_order: block.item.sort_order || 0,
                            visible: block.item.visible !== false,
                        });
                    }
                }
            }

            for (const item of this.items) {
                if (item.type === 'group' || seen.has(item.key) || item.visible) continue;
                seen.add(item.key);
                payload.push({
                    key: item.key,
                    parent_key: item.parent_key || null,
                    zone: item.zone || 'sidebar',
                    sort_order: payload.length,
                    visible: false,
                });
            }

            return payload;
        },

        async save() {
            if (!this.saveUrl || this.saving) return;
            this.saving = true;

            try {
                const formData = new FormData();
                formData.append('method', 'save');
                formData.append('items', JSON.stringify(this._buildPayload()));
                formData.append('zone_settings', JSON.stringify(this.zoneSettings));

                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                if (csrf) formData.append('_token', csrf);

                const resp = await fetch(this.saveUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                });

                if (resp.redirected) {
                    window.location.href = resp.url;
                    return;
                }

                const ct = resp.headers.get('content-type') || '';
                if (ct.includes('application/json')) {
                    const data = await resp.json();
                    if (data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    if (data.message && window.MoonShine?.$toast) {
                        window.MoonShine.$toast.create(data.message, data.messageType || 'success');
                    }
                } else {
                    window.location.reload();
                }
            } catch (e) {
                console.error('[MenuManager]', e);
                window.MoonShine?.$toast?.create(e?.message || 'Ошибка сохранения', 'error');
            } finally {
                this.saving = false;
            }
        },
    }));
});
