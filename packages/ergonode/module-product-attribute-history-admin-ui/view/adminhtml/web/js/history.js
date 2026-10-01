define(['mage/translate', 'Ergonode_ProductAttributeHistoryAdminUi/js/history-state', 'Ergonode_CoreAdminUi/js/history/operations'], function ($t, historyState, operationsView) {
    'use strict';

    var instanceId = 0;

    function escape(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character];
        });
    }

    function text(value) { return escape(value); }

    var actionLabels = {
        connected: $t('Connected'), disconnected: $t('Unmapped'), reconnected: $t('Remapped'),
        included: $t('Included'), excluded: $t('Excluded'), created: $t('Created'), deleted: $t('Deleted'),
        renamed: $t('Renamed'), type_changed: $t('Type changed'), scope_changed: $t('Scope changed')
    };

    function actionNames(actions) {
        return actions.map(function (action) { return $t(actionLabels[action] || action); }).join(', ');
    }

    return function (config, element) {
        var prefix = 'veah-' + (++instanceId);
        var state = config.state || null;
        var page = config.page || {items: [], total: 0, has_more: false};
        var operations = page.items.slice();
        var selectedId = state ? Number(state.operation.operation_id) : 0;
        var selectedChange = null;
        var changesOnly = !state || state.changes_only !== false;
        var loadingState = false;
        var requestVersion = 0;
        var loadingOlder = false;
        var disposed = false;
        var detailsOrigin = null;
        var expanded = new Set();
        var loaded = new Set();
        var pending = new Map();
        var optionErrors = new Set();
        var searchParents = {source: [], target: []};
        var searchVersions = {source: 0, target: 0};
        var searchTimers = {};

        function key(side, code) { return JSON.stringify([side, code]); }

        function request(url, params) {
            var destination = new URL(url, window.location.href);
            Object.keys(params).forEach(function (key) { destination.searchParams.set(key, params[key]); });
            return fetch(destination.href, {
                credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            }).then(function (response) {
                if (!response.ok) { throw new Error($t('History could not be loaded.')); }
                return response.json();
            });
        }

        function section(side, label) {
            return '<section class="veui-panel veah-attributes" aria-labelledby="' + prefix + '-' + side + '">' +
                '<header class="veui-panel-head veui-panel-head-with-tools"><strong class="veui-panel-title" id="' + prefix + '-' + side + '">' +
                '<img class="veah-brand" alt="" src="' + escape(config.icons[side]) + '"><span class="veui-panel-title-text">' + text(label) + '</span></strong>' +
                '<div class="veui-panel-head-tools"><label class="veui-search veui-search-expandable"><span class="veui-search-icon" aria-hidden="true"></span>' +
                '<input type="search" data-role="search" data-side="' + side + '" aria-label="' + escape($t('Search %1 attributes and options').replace('%1', label)) +
                '" placeholder="' + text($t('Search attributes and options...')) + '"></label></div></header>' +
                '<div class="veah-attribute-list" data-role="attributes" data-side="' + side + '" aria-live="polite" tabindex="0" role="region" ' +
                'aria-label="' + escape($t('%1 attributes and options').replace('%1', label)) + '"></div></section>';
        }

        element.innerHTML = '<div class="veui-message veah-message" data-role="message" role="status" hidden></div>' +
            '<div class="veui-toolbar veah-notice" data-role="notice" hidden></div>' +
            '<div class="veui-layout veah-layout">' + section('source', 'Ergonode') + section('target', 'Magento') +
            '<aside class="veui-panel" aria-labelledby="' + prefix + '-operations"><header class="veui-panel-head"><div>' +
            '<strong id="' + prefix + '-operations" class="veui-panel-title">' + text($t('Operations')) + '</strong>' +
            '<span class="veui-panel-subtitle">' + text($t('Choose an operation')) + '</span></div>' +
            '<label class="veah-changes-filter"><input type="checkbox" data-role="changes-only"' + (changesOnly ? ' checked' : '') +
            '><span>' + text($t('Only changes')) + '</span></label></header>' +
            '<div class="veah-operation-list" data-role="operations"></div></aside></div>' +
            '<dialog class="veah-dialog" data-role="dialog" aria-labelledby="' + prefix + '-details">' +
            '<header class="veui-panel-head"><strong class="veui-panel-title" id="' + prefix + '-details">' + text($t('Operation details')) + '</strong>' +
            '<button type="button" class="veah-button" data-action="close" autofocus>' + text($t('Close')) + '</button></header>' +
            '<div data-role="details" class="veah-details"></div></dialog>';

        var dialog = element.querySelector('[data-role="dialog"]');
        var message = element.querySelector('[data-role="message"]');
        var changesToggle = element.querySelector('[data-role="changes-only"]');

        function showMessage(value) {
            message.textContent = value;
            message.hidden = !value;
        }

        function linkedLabel(label, tone) {
            if (tone !== 'disconnected') { return escape(label); }
            return String(label).split(/(\s+)/).map(function (part) {
                return /^\s*$/.test(part) ? escape(part) : '<span class="veah-disconnected-text">' + escape(part) + '</span>';
            }).join('');
        }

        function card(item, side, parent, toggle) {
            var tone = side === 'target' || item.optionChanges.length ? historyState.tone(item) : '';
            var ownTone = item.actions.length ? tone : '';
            var changeActions = Array.from(new Set(item.actions.concat(item.optionChanges.flatMap(function (change) { return change.actions; }))));
            var classes = 'veah-card' + (side === 'source' ? ' veah-card-source' : '') + (tone ? ' is-' + tone : '') +
                (item.excluded && side === 'target' ? ' is-inactive' : '');
            var identity = ' data-entity="' + (parent ? 'option' : 'attribute') + '" data-attribute-code="' + escape(parent || '') + '"';
            var details = (item.change && side === 'target' || item.optionChanges.length) ?
                '<button type="button" class="veah-change" data-action="card-details" data-side="' + side + '" data-code="' + escape(item.code) +
                '"' + identity + ' aria-label="' + escape(item.label + ': ' + actionNames(changeActions)) + '" title="' + escape(actionNames(changeActions)) + '">' +
                (tone === 'connected' ? '<span class="veah-connected-icon veui-connected-icon" aria-hidden="true"></span>' :
                    tone === 'disconnected' ? '<span class="veah-disconnected-icon" aria-hidden="true"></span>' :
                    '<span aria-hidden="true">' + ({excluded: '⊘', deleted: '×', created: '+'}[tone] || '↻') + '</span>') + '</button>' : '';
            var linked = side === 'target' && item.linked ? '<div class="veah-mapping">' +
                '<span class="veah-mapping-arrow" aria-hidden="true">↳</span> ' + linkedLabel(item.linked.label, ownTone) +
                ' <span class="veah-code' + (ownTone === 'disconnected' ? ' veah-disconnected-text' : '') + '">(' + escape(item.linked.code) + ')</span></div>' : '';

            return '<article class="' + classes + '" data-role="entity-card" data-side="' + side + '" data-code="' + escape(item.code) + '"' + identity + ' tabindex="-1">' +
                '<div class="veah-card-body"><div class="veah-card-line"><strong class="veah-label">' + escape(item.label) +
                '</strong><span class="veah-type">' + escape(item.type) + '</span></div><span class="veah-code">' + escape(item.code) + '</span>' + linked + '</div>' + details + (toggle || '') + '</article>';
        }

        function matches(item, query) {
            return [item.label, item.code, item.type, item.linked && item.linked.label, item.linked && item.linked.code]
                .join(' ').toLocaleLowerCase().includes(query);
        }

        function renderAttributes(side) {
            var query = element.querySelector('[data-role="search"][data-side="' + side + '"]').value.toLocaleLowerCase().trim();
            var groups = historyState.groups(state, side).filter(function (group) {
                return matches(group.attribute, query) || group.options.some(function (option) { return matches(option, query); }) ||
                    searchParents[side].includes(group.attribute.code);
            });
            element.querySelector('[data-role="attributes"][data-side="' + side + '"]').innerHTML = groups.length ?
                groups.map(function (group) {
                    var item = group.attribute;
                    var identity = key(side, item.code);
                    var open = expanded.has(identity);
                    var visible = group.options.filter(function (option) { return matches(item, query) || matches(option, query); });
                    var changed = visible.filter(function (option) { return option.actions.length; });
                    var unchanged = visible.filter(function (option) { return !option.actions.length; });
                    var unchangedCount = group.count - group.options.filter(function (option) { return !option.deleted && option.actions.length; }).length;
                    var hasMore = !changesOnly && unchangedCount > 0;
                    var regionId = prefix + '-options-' + side + '-' + encodeURIComponent(item.code);
                    var toggle = hasMore ? '<button type="button" class="veah-expand" data-action="toggle-options" data-side="' + side +
                        '" data-code="' + escape(item.code) + '" aria-expanded="' + open + '" aria-controls="' + escape(regionId) +
                        '" aria-label="' + escape($t('Unchanged options of %1').replace('%1', item.label)) + '">' +
                        '<span>' + text($t('Options')) + '</span><span class="veah-expand-count" aria-hidden="true">' + unchangedCount + '</span>' +
                        '<svg class="veah-expand-chevron" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false">' +
                        '<path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>' : '';
                    var status = optionErrors.has(identity) ? '<button type="button" class="veah-button" data-action="retry-options" data-side="' + side +
                        '" data-code="' + escape(item.code) + '">' + text($t('Options could not be loaded. Try again.')) + '</button>' :
                        pending.has(identity) ? '<p class="veah-empty" role="status">' + text($t('Loading options...')) + '</p>' : '';
                    return '<div class="veah-attribute-group" data-role="attribute-group" data-attribute-code="' + escape(item.code) + '">' +
                        card(item, side, null, toggle) + (changed.length ? '<section class="veah-option-list" aria-label="' +
                            escape($t('Changed options of %1').replace('%1', item.label)) + '">' +
                            changed.map(function (option) { return card(option, side, item.code); }).join('') + '</section>' : '') +
                        (hasMore ? '<section class="veah-option-list" id="' + escape(regionId) + '"' + (open ? '' : ' hidden') +
                            ' aria-label="' + escape($t('Unchanged options of %1').replace('%1', item.label)) + '">' +
                            (open ? status + unchanged.map(function (option) { return card(option, side, item.code); }).join('') : '') + '</section>' : '') + '</div>';
                }).join('') :
                '<p class="veah-empty">' + text(!state ? $t('No recorded state yet.') : query ? $t('No matching attributes or options.') :
                    changesOnly ? $t('No changes to show.') : side === 'source' ? $t('All attributes and options are mapped and included.') : $t('No attributes in this state.')) + '</p>';
        }

        function focusToggle(side, code) {
            var toggle = Array.from(element.querySelectorAll('[data-action="toggle-options"]')).find(function (button) {
                return button.dataset.side === side && button.dataset.code === code;
            });
            if (toggle) { toggle.focus({preventScroll: true}); }
        }

        function loadOptions(side, code) {
            var identity = key(side, code);
            if (pending.has(identity)) { return pending.get(identity); }
            if (loaded.has(identity) || !state.option_counts) { return Promise.resolve(); }
            var current = state;
            optionErrors.delete(identity);
            var promise = request(config.urls.state, {operation_id: selectedId, side: side, attribute_code: code}).then(function (response) {
                if (disposed || current !== state) { return; }
                if (!response.options) { throw new Error('Missing options'); }
                Object.keys(response.options).forEach(function (optionSide) {
                    Object.keys(response.options[optionSide]).forEach(function (parent) {
                        state.options[optionSide][parent] = response.options[optionSide][parent];
                        loaded.add(key(optionSide, parent));
                    });
                });
            }).catch(function () {
                if (!disposed && current === state) { optionErrors.add(identity); }
            }).finally(function () {
                if (disposed || current !== state) { return; }
                pending.delete(identity);
                var active = element.contains(document.activeElement) ? document.activeElement.dataset : {};
                var restoreFocus = active.action === 'toggle-options' || active.action === 'retry-options';
                var activeSide = active.side;
                var activeCode = active.code;
                renderAttributes('source');
                renderAttributes('target');
                if (restoreFocus) { focusToggle(activeSide, activeCode); }
            });
            pending.set(identity, promise);
            return promise;
        }

        function toggleOptions(side, code, retry) {
            var identity = key(side, code);
            if (expanded.has(identity) && !retry) { expanded.delete(identity); }
            else { expanded.add(identity); loadOptions(side, code); }
            renderAttributes(side);
            focusToggle(side, code);
        }

        function renderOperations() {
            var selected = state && state.operation;
            var display = operations.slice();
            if (selected && !display.some(function (operation) { return Number(operation.operation_id) === selectedId; })) {
                display.unshift(selected);
            }
            display = display.filter(function (operation) {
                return !changesOnly || Number(operation.change_count) > 0;
            });
            var current = config.urls.mapping ? '<a class="veah-current" href="' + escape(config.urls.mapping) + '"><span>' +
                text($t('Current state')) + '</span><span aria-hidden="true">↗</span></a>' : '';
            element.querySelector('[data-role="operations"]').innerHTML = current + display.map(function (operation) { return operationsView.card(operation, selectedId); }).join('') +
                (!display.length ? '<p class="veah-empty">' + text(changesOnly && operations.length ? $t('No changes to show.') :
                    $t('New operations will appear here after mappings are saved or synchronized.')) + '</p>' : '') +
                '<div class="veah-pagination"><span>' + escape($t('%1 of %2 operations').replace('%1', operations.length).replace('%2', page.total)) + '</span>' +
                (page.has_more ? '<button type="button" class="veah-button" data-action="older"' + (loadingOlder ? ' disabled' : '') + '>' +
                    text(loadingOlder ? $t('Loading...') : $t('Load 10 older')) + '</button>' : '') + '</div>';
        }

        function renderState() {
            var notice = element.querySelector('[data-role="notice"]');
            notice.textContent = state && !state.options ? $t('This operation does not contain option history.') : '';
            notice.hidden = !notice.textContent;
            renderAttributes('source');
            renderAttributes('target');
            renderOperations();
        }

        function select(id) {
            var requestedMode = changesToggle.checked;
            if (id === selectedId && state && requestedMode === changesOnly && !loadingState) { return Promise.resolve(true); }
            loadingState = true;
            var version = ++requestVersion;
            element.setAttribute('aria-busy', 'true');
            showMessage($t('Loading historical state...'));
            return request(config.urls.state, {operation_id: id, changes_only: requestedMode ? 1 : 0}).then(function (response) {
                if (version !== requestVersion || disposed) { return false; }
                if (!response.state || Number(response.state.operation.operation_id) !== id) {
                    throw new Error($t('History could not be loaded.'));
                }
                state = response.state;
                changesOnly = requestedMode;
                changesToggle.checked = changesOnly;
                expanded.clear();
                loaded.clear();
                pending.clear();
                optionErrors.clear();
                searchParents = {source: [], target: []};
                searchVersions.source++;
                searchVersions.target++;
                selectedId = id;
                selectedChange = null;
                renderState();
                ['source', 'target'].forEach(function (side) {
                    input({target: element.querySelector('[data-role="search"][data-side="' + side + '"]')});
                });
                showMessage('');
                if (config.updateUrl !== false) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('operation_id', id);
                    window.history.replaceState(null, '', url.href);
                }
                return true;
            }).catch(function () {
                if (version === requestVersion && !disposed) {
                    changesToggle.checked = changesOnly;
                    showMessage($t('History could not be loaded. Try again.'));
                }
                return false;
            }).finally(function () {
                if (version === requestVersion && !disposed) {
                    loadingState = false;
                    element.removeAttribute('aria-busy');
                }
            });
        }

        function snapshotMarkup(snapshot, label) {
            if (!snapshot) { return '<section><h4>' + text(label) + '</h4><p class="veah-empty">' + text($t('Not present')) + '</p></section>'; }
            var fieldLabels = {Label: $t('Label'), Code: $t('Code'), Type: $t('Type'), Scope: $t('Scope'), Mapping: $t('Mapping'), Visibility: $t('Visibility')};
            var values = {
                Label: snapshot.label, Code: snapshot.code, Type: snapshot.type, Scope: snapshot.scope,
                Mapping: snapshot.mapped_code ? (snapshot.mapped_attribute_code ? snapshot.mapped_attribute_code + ' / ' : '') +
                    snapshot.mapped_code : $t('Unmapped'), Visibility: snapshot.active ? $t('Included') : $t('Excluded')
            };
            return '<section><h4>' + text(label) + '</h4><dl>' + Object.keys(values).map(function (key) {
                return '<dt>' + text(fieldLabels[key]) + '</dt><dd>' + escape(values[key]) + '</dd>';
            }).join('') + '</dl></section>';
        }

        function renderDetails() {
            var changes = state ? state.changes : [];
            if (!changes.length) {
                element.querySelector('[data-role="details"]').innerHTML = '<p class="veah-empty">' + text($t('The operation did not change attribute or option mappings or metadata.')) + '</p>';
                return;
            }
            selectedChange = selectedChange || changes[0];
            element.querySelector('[data-role="details"]').innerHTML = '<div class="veah-change-list">' + changes.map(function (change, index) {
                var snapshot = change.after || change.before;
                return '<button type="button" class="veah-change-item' + (change === selectedChange ? ' is-selected' : '') +
                    '" data-action="change" data-index="' + index + '" aria-pressed="' + (change === selectedChange) + '"><strong>' +
                    escape(snapshot.label) + (change.entity === 'option' ? ' · ' + escape(change.attribute_code) : '') + '</strong><span>' + text(change.side === 'source' ? 'Ergonode' : 'Magento') + ' · ' + escape(actionNames(change.actions)) + '</span></button>';
            }).join('') + '</div><div class="veah-change-detail"><h3>' + text($t('Selected change')) + '</h3><p>' + escape(actionNames(selectedChange.actions)) + '</p>' +
                '<div class="veah-before-after">' + snapshotMarkup(selectedChange.before, $t('Before')) + snapshotMarkup(selectedChange.after, $t('After')) + '</div>' +
                (historyState.findVisibleChange(changeView(selectedChange), selectedChange) ? '<button type="button" class="veah-button" data-action="show">' + text(selectedChange.entity === 'option' ? $t('Show option') : $t('Show attribute')) + '</button>' : '') + '</div>';
        }

        function openDetails(id, change) {
            detailsOrigin = id;
            return select(id).then(function (loaded) {
                if (!loaded || disposed) { return; }
                selectedChange = change || null;
                renderDetails();
                if (!dialog.open) { dialog.showModal(); }
            });
        }

        function closeDetails() {
            dialog.close();
            var origin = element.querySelector('[data-action="details"][data-id="' + detailsOrigin + '"]');
            if (origin) { origin.focus(); }
        }

        function changeView(change) {
            return change.entity === 'option' ? historyState.optionView(state, {side: change.side, code: change.attribute_code}) : state;
        }

        function showAttribute() {
            var destination = historyState.findVisibleChange(changeView(selectedChange), selectedChange);
            if (!destination) { return; }
            closeDetails();
            element.querySelector('[data-role="search"][data-side="' + destination.side + '"]').value = '';
            renderAttributes(destination.side);
            var row = Array.from(element.querySelectorAll('[data-role="entity-card"]')).find(function (item) {
                return item.dataset.side === destination.side && item.dataset.code === destination.code &&
                    item.dataset.entity === (selectedChange.entity === 'option' ? 'option' : 'attribute') &&
                    (selectedChange.entity !== 'option' || item.dataset.attributeCode === changeView(selectedChange).parents[destination.side]);
            });
            if (row) {
                row.classList.add('is-focused');
                row.scrollIntoView({block: 'center', behavior: 'smooth'});
                row.focus({preventScroll: true});
            }
        }

        function loadOlder() {
            if (loadingOlder || !page.has_more || !operations.length) { return; }
            loadingOlder = true;
            renderOperations();
            request(config.urls.operations, {before_id: operations[operations.length - 1].operation_id}).then(function (response) {
                if (disposed) { return; }
                page = response.page;
                var ids = new Set(operations.map(function (operation) { return Number(operation.operation_id); }));
                operations = operations.concat(page.items.filter(function (operation) { return !ids.has(Number(operation.operation_id)); }));
                showMessage('');
            }).catch(function () {
                if (!disposed) { showMessage($t('History could not be loaded. Try again.')); }
            }).finally(function () {
                loadingOlder = false;
                if (!disposed) { renderOperations(); }
            });
        }

        function click(event) {
            var button = event.target.closest('[data-action]');
            if (!button || !element.contains(button)) { return; }
            var id = Number(button.dataset.id);
            switch (button.dataset.action) {
                case 'toggle-options': toggleOptions(button.dataset.side, button.dataset.code, false); break;
                case 'retry-options': toggleOptions(button.dataset.side, button.dataset.code, true); break;
                case 'select': select(id); break;
                case 'details': openDetails(id); break;
                case 'close': closeDetails(); break;
                case 'older': loadOlder(); break;
                case 'show': showAttribute(); break;
                case 'change':
                    selectedChange = state.changes[Number(button.dataset.index)];
                    renderDetails();
                    element.querySelector('[data-action="change"][aria-pressed="true"]').focus();
                    break;
                case 'card-details':
                    var view = button.dataset.entity === 'option' ?
                        historyState.optionView(state, {side: button.dataset.side, code: button.dataset.attributeCode}) : state;
                    var item = historyState.rows(view, button.dataset.side, true).find(function (row) { return row.code === button.dataset.code; });
                    if (item) { openDetails(selectedId, item.change || item.optionChanges[0]); }
                    break;
            }
        }

        function input(event) {
            if (event.target.dataset.role !== 'search') { return; }
            var side = event.target.dataset.side;
            var query = event.target.value.toLocaleLowerCase().trim();
            var version = ++searchVersions[side];
            clearTimeout(searchTimers[side]);
            searchParents[side] = [];
            renderAttributes(side);
            if (changesOnly || !query || !state || !state.option_counts) { return; }
            var current = state;
            searchTimers[side] = setTimeout(function () {
                request(config.urls.state, {operation_id: selectedId, side: side, search: query}).then(function (response) {
                    if (disposed || current !== state || version !== searchVersions[side]) { return; }
                    searchParents[side] = response.parents || [];
                    renderAttributes(side);
                }).catch(function () {
                    if (!disposed && current === state && version === searchVersions[side]) {
                        showMessage($t('History could not be loaded. Try again.'));
                    }
                });
            }, 200);
        }

        function filterChanged() {
            if (selectedId) { select(selectedId); }
            else { changesOnly = changesToggle.checked; }
        }

        changesToggle.addEventListener('change', filterChanged);
        element.addEventListener('click', click);
        element.addEventListener('input', input);
        dialog.addEventListener('cancel', function (event) { event.preventDefault(); closeDetails(); });
        dialog.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeDetails();
            }
        });
        renderState();
        if (!state && config.requested_operation_id) { showMessage($t('Operation not found. Select another operation.')); }

        return {destroy: function () {
            disposed = true;
            requestVersion++;
            Object.keys(searchTimers).forEach(function (side) { clearTimeout(searchTimers[side]); });
            changesToggle.removeEventListener('change', filterChanged);
            element.removeEventListener('click', click);
            element.removeEventListener('input', input);
            if (dialog.open) { dialog.close(); }
        }};
    };
});
