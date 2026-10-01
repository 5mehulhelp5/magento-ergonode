define(['mage/translate', 'Ergonode_CategoryAttributeHistoryAdminUi/js/history-state', 'Ergonode_CoreAdminUi/js/history/operations'], function ($t, historyState, operationsView) {
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
        renamed: $t('Renamed'), type_changed: $t('Type changed'), scope_changed: $t('Scope changed'),
        draft_added: $t('Added to draft mapping'), draft_removed: $t('Removed from draft mapping')
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
                '<input type="search" data-role="search" data-side="' + side + '" aria-label="' + escape($t('Search %1 attributes').replace('%1', label)) +
                '" placeholder="' + text($t('Search attributes...')) + '"></label></div></header>' +
                '<div class="veah-attribute-list" data-role="attributes" data-side="' + side + '" aria-live="polite"></div></section>';
        }

        element.innerHTML = '<div class="veui-message veah-message" data-role="message" role="status" hidden></div>' +
            '<p class="veah-empty" data-role="state-description"></p>' +
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

        function card(item, side) {
            var isNew = item.change && item.change.side === side && item.change.code === item.code &&
                item.change.actions.includes('created');
            var tone = side === 'target' ? historyState.tone(item) : isNew ? 'created' : '';
            var classes = 'veah-card' + (side === 'source' ? ' veah-card-source' : '') + (tone ? ' is-' + tone : '') +
                (item.excluded && side === 'target' ? ' is-inactive' : '') + (item.is_draft ? ' is-draft' : '');
            var details = item.change && (side === 'target' || isNew) ?
                '<button type="button" class="veah-change" data-action="card-details" data-side="' + side + '" data-code="' + escape(item.code) +
                '" aria-label="' + escape(item.label + ': ' + actionNames(item.actions)) + '" title="' + escape(actionNames(item.actions)) + '">' +
                (tone === 'connected' ? '<span class="veah-connected-icon veui-connected-icon" aria-hidden="true"></span>' :
                    tone === 'disconnected' ? '<span class="veah-disconnected-icon" aria-hidden="true"></span>' :
                    '<span aria-hidden="true">' + ({excluded: '⊘', deleted: '×', created: '+'}[tone] || '↻') + '</span>') + '</button>' : '';
            var linked = side === 'target' && item.linked ? '<div class="veah-mapping">' +
                '<span class="veah-mapping-arrow" aria-hidden="true">↳</span> ' + linkedLabel(item.linked.label, tone) +
                ' <span class="veah-code' + (tone === 'disconnected' ? ' veah-disconnected-text' : '') + '">(' + escape(item.linked.code) + ')</span></div>' : '';
            var draft = item.is_draft ? '<div class="veah-mapping" data-role="draft-mapping">' +
                text($t('Draft mapping — awaiting connection')) + '</div>' : '';
            var badge = isNew ? '<span class="veah-new" data-role="new-attribute">' + text($t('New')) + '</span>' : '';

            return '<article class="' + classes + '" data-role="entity-card" data-side="' + side + '" data-code="' + escape(item.code) + '" tabindex="-1">' +
                '<div class="veah-card-body"><div class="veah-card-line"><strong class="veah-label">' + escape(item.label) +
                '</strong>' + badge + '<span class="veah-type">' + escape(item.type) + '</span></div><span class="veah-code">' + escape(item.code) + '</span>' + linked + draft + '</div>' + details + '</article>';
        }

        function renderAttributes(side) {
            var query = element.querySelector('[data-role="search"][data-side="' + side + '"]').value.toLocaleLowerCase().trim();
            var items = historyState.rows(state, side).filter(function (item) {
                return [item.label, item.code, item.type, item.linked && item.linked.label, item.linked && item.linked.code]
                    .join(' ').toLocaleLowerCase().includes(query);
            });
            element.querySelector('[data-role="attributes"][data-side="' + side + '"]').innerHTML = items.length ?
                items.map(function (item) { return card(item, side); }).join('') :
                '<p class="veah-empty">' + text(!state ? $t('No recorded state yet.') : query ? $t('No matching attributes.') :
                    changesOnly ? $t('No changes to show.') : side === 'source' ? $t('All attributes are mapped and included.') : $t('No attributes in this state.')) + '</p>';
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
            element.querySelector('[data-role="state-description"]').textContent = state ?
                (changesOnly ? $t('Changes made by the selected operation.') :
                    $t('Attribute state after the selected operation, including unmapped attributes.')) +
                (historyState.lacksDraftStatus(state) ? ' ' + $t('This older entry does not include draft mapping status.') : '') : '';
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
                selectedId = id;
                selectedChange = null;
                renderState();
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
                Mapping: snapshot.mapped_code || (snapshot.is_draft ? $t('Draft mapping — awaiting connection') : $t('Unmapped')),
                Visibility: snapshot.active ? $t('Included') : $t('Excluded')
            };
            return '<section><h4>' + text(label) + '</h4><dl>' + Object.keys(values).map(function (key) {
                return '<dt>' + text(fieldLabels[key]) + '</dt><dd>' + escape(values[key]) + '</dd>';
            }).join('') + '</dl></section>';
        }

        function renderDetails() {
            var changes = state ? state.changes : [];
            if (!changes.length) {
                element.querySelector('[data-role="details"]').innerHTML = '<p class="veah-empty">' + text($t('No changes were recorded for this operation.')) +
                    (historyState.lacksDraftStatus(state) ? ' ' + text($t('This older entry does not include draft mapping status.')) : '') + '</p>';
                return;
            }
            selectedChange = selectedChange || changes[0];
            element.querySelector('[data-role="details"]').innerHTML = '<div class="veah-change-list">' + changes.map(function (change, index) {
                var snapshot = change.after || change.before;
                return '<button type="button" class="veah-change-item' + (change === selectedChange ? ' is-selected' : '') +
                    '" data-action="change" data-index="' + index + '" aria-pressed="' + (change === selectedChange) + '"><strong>' +
                    escape(snapshot.label) + '</strong><span>' + text(change.side === 'source' ? 'Ergonode' : 'Magento') + ' · ' + escape(actionNames(change.actions)) + '</span></button>';
            }).join('') + '</div><div class="veah-change-detail"><h3>' + text($t('Selected change')) + '</h3><p>' + escape(actionNames(selectedChange.actions)) + '</p>' +
                '<div class="veah-before-after">' + snapshotMarkup(selectedChange.before, $t('Before')) + snapshotMarkup(selectedChange.after, $t('After')) + '</div>' +
                (historyState.findVisibleChange(state, selectedChange) ? '<button type="button" class="veah-button" data-action="show">' + text($t('Show attribute')) + '</button>' : '') + '</div>';
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

        function showAttribute() {
            var destination = historyState.findVisibleChange(state, selectedChange);
            if (!destination) { return; }
            closeDetails();
            element.querySelector('[data-role="search"][data-side="' + destination.side + '"]').value = '';
            renderAttributes(destination.side);
            var row = Array.from(element.querySelectorAll('[data-role="entity-card"]')).find(function (item) {
                return item.dataset.side === destination.side && item.dataset.code === destination.code;
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
                    var item = historyState.rows(state, button.dataset.side).find(function (row) { return row.code === button.dataset.code; });
                    if (item) { openDetails(selectedId, item.change); }
                    break;
            }
        }

        function input(event) {
            if (event.target.dataset.role === 'search') { renderAttributes(event.target.dataset.side); }
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
            changesToggle.removeEventListener('change', filterChanged);
            element.removeEventListener('click', click);
            element.removeEventListener('input', input);
            if (dialog.open) { dialog.close(); }
        }};
    };
});
