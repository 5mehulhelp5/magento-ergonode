define([
    'jquery',
    'mage/translate',
    'Ergonode_CategoryAdminUi/js/category-tree-mapping-state',
    'Ergonode_CategoryAdminUi/js/category-tree-bulk-actions',
    'Ergonode_CategoryAdminUi/js/category-tree-state',
    'Ergonode_CategoryAdminUi/js/category-tree-view',
    'Ergonode_CategoryAdminUi/js/category-tree-collapse-storage',
    'Ergonode_CategoryAdminUi/js/category-tree-subtree-mapping',
    'Ergonode_CategoryAdminUi/js/category-tree-native-drag',
    'Ergonode_CategoryAdminUi/js/category-tree-configuration-order',
    'Ergonode_CategoryAdminUi/js/category-tree-new-mapping',
    'Ergonode_CoreAdminUi/js/workspace',
    'Ergonode_CoreAdminUi/js/entity-options',
    'Ergonode_CoreAdminUi/js/workspace-context',
    'Ergonode_CoreAdminUi/js/visibility-toggle',
    'Ergonode_CoreAdminUi/js/unsaved-navigation',
    'Ergonode_CategoryAdminUi/js/category-tree-operation-state',
    'Ergonode_CategoryAdminUi/js/save-recovery',
    'Ergonode_CategoryAdminUi/js/category-source-issues'
], function (
    $,
    $t,
    mappingState,
    bulkActions,
    treeState,
    treeView,
    collapseStorage,
    subtreeMapping,
    nativeDrag,
    configurationOrder,
    newMapping,
    workspace,
    entityOptions,
    workspaceContext,
    visibilityToggle,
    unsavedNavigation,
    categoryOperationState,
    saveWithRecovery,
    createSourceIssues
) {
    'use strict';

    return function (config, element) {
        var $root = $(element);
        var categories = normalizeCategories(config.categories || []);
        var magentoCategories = normalizeMagentoCategories(config.magento_categories || []);
        var persistedMappings = collectPersistedMappings(categories, true);
        var categoryTreeId = Number(config.category_tree_id || 0);
        var collapsedSourceNodes = {};
        var collapsedMagentoNodes = {};
        var browserStorage = getBrowserStorage();
        var showBlocked = {ergo: false, magento: false};
        var hideMappedErgonode = true;
        var dirty = false;
        var pendingCategoryTreeRequest = null;
        var saveRecoveryHandler = null;
        var operationState;
        var pendingSave = null;
        var mappingHintSequence = 0;
        var pendingSaveHintSequence = 0;
        var excludedCategoryHintSequence = 0;
        var bulkSelection = {ergo: {}, magento: {}};
        var scope = workspace.mount(element);
        var unsavedChangesGuard;
        var sourceTree;
        var magentoTree;
        var mappingsByMagentoId;
        var mappingIndex;
        var removalsByMagentoId;
        var batchDepth = 0;
        var renderPending = false;
        var searchTimers = {ergo: null, magento: null};
        var renderSourceIssues;
        var sourceRefreshPending = false;
        var extensionOperationPending = false;

        if (!scope.claim(element, 'category-tree-mapping')) {
            return scope;
        }

        var context = workspaceContext.create(scope, element, {
            publish: false,
            message: {
                containerSelector: '[data-role="message"]',
                textSelector: '[data-role="message-text"]',
                toneClasses: {
                    success: 'vec-message-success',
                    error: 'vec-message-error'
                }
            }
        });
        var messageBus = context.message;
        var showMessage = messageBus.show;
        var $ergoList = $root.find('[data-role="ergo-list"]');
        var $magentoList = $root.find('[data-role="magento-list"]');
        var $saveButton = $root.find('[data-role="save-categories"]');
        var $autoMapButton = $root.find('[data-role="auto-map-categories"]');
        var $ergoSearch = $root.find('[data-role="ergo-search"]');
        var $magentoSearch = $root.find('[data-role="magento-search"]');
        var $configurationList = $root.find('[data-role="category-tree-configuration-list"]');
        var $deleteCategoryTreeForm = $root.find('[data-role="delete-category-tree-form"]');

        init();

        function init() {
            config.urls = config.urls || {};
            config.form_key = config.form_key || window.FORM_KEY || '';
            operationState = categoryOperationState.create(element);
            scope.cleanup(operationState.destroy);
            if (config.urls.auto_map) {
                config.mapping_actions = (config.mapping_actions || []).concat([buildAutoMapAction()]);
            }
            initializeBulkOptions();
            initializeConfigurationOptions();
            visibilityToggle.initialize(element);
            $autoMapButton = $root.find('[data-role="auto-map-categories"]');
            entityOptions.bind(scope, element);

            initializeCollapsedNodes();
            bindEvents();
            bindUnsavedNavigation();
            newMapping.init(config, element, scope);
            renderSourceIssues = createSourceIssues(element, {
                retry: function (button) { refreshCategories($(button)); },
                disable: disableSourceMapping
            });
            updateSourceIssues(config);
            renderAll();
            exposeExtensionApi();
            rememberCurrentCategoryTree();

            if (config.status && config.status.error) {
                showMessage('error', config.status.error);
            }
        }

        function bindEvents() {
            $ergoSearch.on('input.ergonodeCategoryTree', function () {
                scheduleSearch('ergo', renderErgonodeList);
            });
            $magentoSearch.on('input.ergonodeCategoryTree', function () {
                scheduleSearch('magento', renderMagentoList);
            });
            $root.on('change.ergonodeCategoryTree', '[data-role="bulk-category-select"]', function () {
                var source = String($(this).data('bulk-source') || '');
                var identifier = String($(this).data('identifier') || '');
                var checked = $(this).prop('checked');

                if (!bulkSelection[source] || !identifier) {
                    return;
                }
                setBulkBranchSelected(source, identifier, checked);
                updateBulkControls();
            });
            $root.on('click.ergonodeCategoryTree', '[data-role="visibility-toggle"]', function (event) {
                var source = String($(this).data('bulk-source') || '');

                if (!Object.prototype.hasOwnProperty.call(showBlocked, source) || this.disabled) {
                    return;
                }
                event.preventDefault();
                showBlocked[source] = visibilityToggle.toggle(this);
                if (source === 'ergo') {
                    renderErgonodeList();
                } else {
                    renderMagentoList();
                }
            });
            $root.on('click.ergonodeCategoryTree', '[data-role="mapped-visibility-toggle"]', function (event) {
                if (this.disabled) {
                    return;
                }
                event.preventDefault();
                hideMappedErgonode = !hideMappedErgonode;
                syncMappedVisibilityControl(this);
                renderErgonodeList();
            });
            $root.on('click.ergonodeCategoryTree', '[data-role="unmap-selected-categories"]', function (event) {
                event.preventDefault();
                unmapSelectedCategories(String($(this).data('bulk-source') || ''));
            });
            $root.on('click.ergonodeCategoryTree', '[data-role="refresh-categories"]', function (event) {
                event.preventDefault();
                refreshCategories($(this));
            });
            $root.on('click.ergonodeCategoryTree', '[data-role="auto-map-categories"]', function (event) {
                event.preventDefault();
                previewCategories($(this), $t('Auto-mapowanie zakończone.'));
            });
            configurationOrder.bind($configurationList[0], {
                save: saveConfigurationOrder,
                successMessage: $t('Zapisano kolejność konfiguracji.'),
                failureMessage: $t('Nie udało się zapisać kolejności konfiguracji.'),
                onSuccess: function (message) {
                    showMessage('success', message);
                },
                onError: function (message) {
                    showMessage('error', message);
                }
            }, scope);
            $deleteCategoryTreeForm.on('submit.ergonodeCategoryTree', function (event) {
                if (!window.confirm($t('Czy na pewno usunąć tę konfigurację drzewa kategorii?'))) {
                    event.preventDefault();
                }
            });
            $root.on('click.ergonodeCategoryTree', '[data-role="category-tree-configuration-link"]', function (event) {
                var $card;
                var nextCategoryTreeId;

                if (event.which !== 1 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }
                if (!config.current_category_tree) {
                    return;
                }

                event.preventDefault();
                $card = $(this).closest('[data-role="category-tree-configuration"]');
                nextCategoryTreeId = Number($card.data('category-tree-id') || 0);

                loadCategoryTree(nextCategoryTreeId, String(this.href || ''));
            });
            nativeDrag.bind($root, mapDroppedCategory, scope);

            $root.on('click.ergonodeCategoryTree', '[data-role="toggle-children"]', function (event) {
                var $toggle = $(this);
                var tree = String($toggle.data('tree') || '');
                var key = String($toggle.data('node-key') || '');

                event.preventDefault();
                event.stopPropagation();

                if (!key) {
                    return;
                }

                if (tree === 'source') {
                    treeState.toggle(collapsedSourceNodes, key);
                    saveCollapsedNodes();
                    renderErgonodeList();
                } else if (tree === 'magento') {
                    treeState.toggle(collapsedMagentoNodes, key);
                    saveCollapsedNodes();
                    renderMagentoList();
                }
            });

            $root.on('click.ergonodeCategoryTree', '[data-role="unmap-category"]', function (event) {
                event.preventDefault();
                setMapping(String($(this).data('code') || ''), null);
            });

            $root.on('click.ergonodeCategoryTree', '[data-role="delete-snapshot-category"]', function (event) {
                var label = String($(this).data('label') || '');

                event.preventDefault();
                event.stopPropagation();
                if (!window.confirm(
                    $t('Remove "%1" from this list? The Magento category will not be deleted.')
                        .replace('%1', label)
                )) {
                    return;
                }
                deleteSnapshotCategory(String($(this).data('code') || ''), $(this));
            });

            $root.on('click.ergonodeCategoryTree', '[data-role="category-active-toggle"]', function (event) {
                event.preventDefault();
                event.stopPropagation();
                toggleCategoryActive(
                    String($(this).data('source') || ''),
                    String($(this).data('identifier') || '')
                );
            });

            $saveButton.on('click.ergonodeCategoryTree', saveLayout);
            scope.cleanup(function () {
                window.clearTimeout(searchTimers.ergo);
                window.clearTimeout(searchTimers.magento);
                $ergoSearch.off('.ergonodeCategoryTree');
                $magentoSearch.off('.ergonodeCategoryTree');
                $deleteCategoryTreeForm.off('.ergonodeCategoryTree');
                $root.off('.ergonodeCategoryTree');
                $saveButton.off('.ergonodeCategoryTree');
            });
        }

        function saveConfigurationOrder(categoryTreeIds) {
            return new Promise(function (resolve, reject) {
                post(config.urls.settings_reorder, {category_tree_ids: categoryTreeIds})
                    .done(resolve)
                    .fail(reject);
            });
        }

        function bindUnsavedNavigation() {
            unsavedChangesGuard = unsavedNavigation.bind(scope, {
                isDirty: function () {
                    return dirty;
                },
                navigate: function (url) {
                    window.location.assign(url);
                },
                save: saveBeforeNavigation,
                shouldHandleLink: function (anchor) {
                    return !anchor.matches('[data-role="category-tree-configuration-link"]');
                }
            });
        }

        function saveBeforeNavigation() {
            return new Promise(function (resolve, reject) {
                saveLayout()
                    .done(function (response) {
                        if (response && response.success) {
                            resolve(response);
                            return;
                        }
                        reject(response || {});
                    })
                    .fail(reject);
            });
        }

        function loadCategoryTree(nextCategoryTreeId, mappingUrl) {
            if (nextCategoryTreeId <= 0 || nextCategoryTreeId === categoryTreeId) {
                return;
            }
            if (dirty && unsavedChangesGuard) {
                unsavedChangesGuard.request({
                    navigate: function () {
                        requestCategoryTree(nextCategoryTreeId, mappingUrl);
                    },
                    onCancel: updateSelectedCategoryTree,
                    onSaveError: updateSelectedCategoryTree
                });
                return;
            }

            requestCategoryTree(nextCategoryTreeId, mappingUrl);
        }

        function requestCategoryTree(nextCategoryTreeId, mappingUrl) {
            var request;

            if (pendingCategoryTreeRequest) {
                pendingCategoryTreeRequest.abort();
            }

            setConfigurationLoading(true);
            request = $.ajax({
                url: config.urls.load,
                type: 'GET',
                cache: false,
                dataType: 'json',
                timeout: typeof timeout === 'number' ? timeout : 60000,
                data: {category_tree_id: nextCategoryTreeId}
            });
            pendingCategoryTreeRequest = request;

            request.done(function (response) {
                if (!response || !response.success || !response.config) {
                    showMessage('error', response && response.message
                        ? response.message
                        : $t('Nie udało się wczytać konfiguracji drzewa.'));
                    return;
                }

                applyCategoryTreeConfig(response.config);
                if (mappingUrl && window.history && window.history.replaceState) {
                    window.history.replaceState(
                        $.extend({}, window.history.state || {}, {ergonodeCategoryTreeId: categoryTreeId}),
                        '',
                        mappingUrl
                    );
                }
            }).fail(function (xhr, status) {
                if (status !== 'abort') {
                    showMessage('error', $t('Nie udało się wczytać konfiguracji drzewa.'));
                }
            }).always(function () {
                if (pendingCategoryTreeRequest === request) {
                    pendingCategoryTreeRequest = null;
                    setConfigurationLoading(false);
                }
            });
        }

        function applyCategoryTreeConfig(nextConfig) {
            var currentCategoryTree = nextConfig.current_category_tree || {};

            categoryTreeId = Number(nextConfig.category_tree_id || 0);
            config.category_tree_id = categoryTreeId;
            config.current_category_tree = currentCategoryTree;
            config.tree_code = String(nextConfig.tree_code || currentCategoryTree.tree_code || '');
            config.magento_root_id = Number(nextConfig.magento_root_id || currentCategoryTree.root_category_id || 0);
            config.status = nextConfig.status || {};
            updateSourceIssues(nextConfig);
            $root.trigger('ergonode:category-mapping:config', [nextConfig]);
            dirty = false;
            showBlocked = {ergo: false, magento: false};
            hideMappedErgonode = true;
            $ergoSearch.val('');
            $magentoSearch.val('');
            $root.find('[data-role="visibility-toggle"]').each(function () {
                visibilityToggle.setVisible(this, false);
            });

            replaceModels(nextConfig, true);
            updateSelectedCategoryTree();
            exposeExtensionApi();

            messageBus.hide();
            if (config.status.error) {
                showMessage('error', config.status.error);
            }
        }

        function updateSelectedCategoryTree() {
            $configurationList.find('[data-role="category-tree-configuration"]').each(function () {
                var selected = Number($(this).data('category-tree-id') || 0) === categoryTreeId;

                $(this).toggleClass('is-selected', selected);
                $(this).find('[data-role="category-tree-configuration-link"]')
                    .attr('aria-current', selected ? 'page' : 'false');
            });
        }

        function setConfigurationLoading(loading) {
            $root.toggleClass('is-configuration-loading', !!loading).attr('aria-busy', loading ? 'true' : 'false');
            $configurationList.attr('aria-busy', loading ? 'true' : 'false');
        }

        function rememberCurrentCategoryTree() {
            if (!categoryTreeId || !window.history || !window.history.replaceState) {
                return;
            }

            window.history.replaceState(
                $.extend({}, window.history.state || {}, {ergonodeCategoryTreeId: categoryTreeId}),
                '',
                window.location.href
            );
        }

        function normalizeCategories(items) {
            return items.map(function (item, index) {
                var sortOrder = item.sort_order;
                var sourceSortOrder = item.source_sort_order;

                if (sortOrder === null || sortOrder === undefined) {
                    sortOrder = index;
                }
                if (sourceSortOrder === null || sourceSortOrder === undefined) {
                    sourceSortOrder = sortOrder;
                }

                return {
                    code: String(item.code || ''),
                    ergonode_category_id: item.ergonode_category_id
                        ? String(item.ergonode_category_id)
                        : null,
                    label: String(item.label || item.code || ''),
                    parent_code: item.parent_code ? String(item.parent_code) : null,
                    source_parent_code: item.source_parent_code || item.parent_code
                        ? String(item.source_parent_code || item.parent_code)
                        : null,
                    sort_order: Number(sortOrder),
                    source_sort_order: Number(sourceSortOrder),
                    magento_category_id: item.magento_category_id ? Number(item.magento_category_id) : null,
                    sync_status: String(item.sync_status || 'pending'),
                    sync_message: item.sync_message ? String(item.sync_message) : '',
                    mapping_source: String(item.mapping_source || ''),
                    active: item.active !== false,
                    extension_data: $.extend(true, {}, item.extension_data || {})
                };
            }).filter(function (item) {
                return item.code;
            });
        }

        function normalizeMagentoCategories(items) {
            return items.map(function (item) {
                return {
                    id: Number(item.id || 0),
                    parent_id: Number(item.parent_id || 0),
                    label: String(item.label || item.id || ''),
                    path: String(item.path || ''),
                    level: Number(item.level || 0),
                    position: Number(item.position || 0),
                    url_key: String(item.url_key || ''),
                    active: item.active !== false
                };
            }).filter(function (item) {
                return item.id > 0;
            });
        }

        function scheduleSearch(source, render) {
            window.clearTimeout(searchTimers[source]);
            searchTimers[source] = window.setTimeout(render, 150);
        }

        function rebuildIndexes() {
            mappingIndex = mappingState.createIndex(categories);
            sourceTree = treeView.index(categories, 'code', 'source_parent_code', sortBySourceOrder);
            magentoTree = treeView.index(magentoCategories, 'id', 'parent_id', sortMagento);
            mappingsByMagentoId = Object.create(null);
            removalsByMagentoId = Object.create(null);
            categories.forEach(function (category) {
                var id = Number(category.magento_category_id || 0);
                var previous = Number(persistedMappings[category.code] || 0);

                if (id && !mappingsByMagentoId[id]) {
                    mappingsByMagentoId[id] = category;
                }
                if (previous && previous !== id && !removalsByMagentoId[previous]) {
                    removalsByMagentoId[previous] = category;
                }
            });
        }

        function batchUpdate(callback) {
            batchDepth += 1;
            try {
                return callback();
            } finally {
                batchDepth -= 1;
                if (!batchDepth && renderPending) {
                    renderAll();
                }
            }
        }

        function renderAll() {
            if (batchDepth) {
                renderPending = true;
                return;
            }
            renderPending = false;
            rebuildIndexes();
            renderErgonodeList();
            renderMagentoList();
            updateCounts();
            updateButtons();
        }

        function initializeBulkOptions() {
            if (!$root.find('.vec-source-tools [data-bulk-options-source="ergo"]').length) {
                $root.find('.vec-source-tools').append(buildBulkOptions('ergo'));
            }
            if (!$root.find('.vec-target-tools [data-bulk-options-source="magento"]').length) {
                $root.find('[data-role="category-mapping-actions"]').append(buildBulkOptions('magento'));
            }
        }

        function initializeConfigurationOptions() {
            var canEdit = $root.attr('data-can-edit-category-tree') === '1';

            $configurationList.find('[data-role="category-tree-configuration"]').each(function () {
                var $card = $(this);
                var slot = $card.find('[data-role="configuration-options-slot"]')[0];
                var label;
                var actions = [];
                var menu;

                if (!slot || slot.children.length > 0) {
                    return;
                }

                label = String($(slot).data('configuration-label') || '');
                if (canEdit) {
                    actions.push(buildEditConfigurationAction(label));
                }
                if (actions.length === 0) {
                    return;
                }

                menu = entityOptions.create({
                    actions: actions,
                    menuLabel: $t('Tree options: %1').replace('%1', label)
                });
                menu.classList.add('vec-configuration-options');
                slot.appendChild(menu);
            });
        }

        function buildBulkOptions(source) {
            var sourceLabel = source === 'ergo' ? 'Ergonode' : 'Magento';
            var menuLabel = $t('Category actions: %1').replace('%1', sourceLabel);
            var actions = [
                source === 'ergo' ? buildRefreshAction() : null,
                {
                    role: 'visibility-toggle',
                    className: 'veui-visibility-control',
                    iconClass: 'veui-visibility-icon',
                    label: $t('Excluded'),
                    disabled: true,
                    attributes: {
                        'data-bulk-source': source,
                        'data-show-hint': $t('Pokaż kategorie pominięte w mapowaniu'),
                        'data-hide-hint': $t('Ukryj kategorie pominięte w mapowaniu'),
                        'aria-pressed': 'false'
                    }
                },
                source === 'ergo' ? {
                    role: 'mapped-visibility-toggle',
                    className: 'veui-visibility-control',
                    iconClass: 'vec-connected-icon veui-connected-icon',
                    label: $t('Connected'),
                    attributes: {
                        'data-show-hint': $t('Show connected categories'),
                        'data-hide-hint': $t('Hide connected categories'),
                        'aria-pressed': 'true'
                    }
                } : null
            ];
            var menu;
            var summary;

            actions = (source === 'magento' ? (config.mapping_actions || []) : []).concat(actions.filter(Boolean));
            actions.push({
                role: 'unmap-selected-categories',
                className: 'vec-unmap-selected-categories',
                iconClass: 'vec-unmap-icon',
                label: $t('Disconnect'),
                disabled: true,
                attributes: {'data-bulk-source': source}
            });
            menu = entityOptions.create({actions: actions, menuLabel: menuLabel});
            menu.classList.add(source === 'magento' ? 'veui-split-button-options' : 'vec-bulk-options');
            if (source === 'magento') {
                menu.querySelector('.veui-entity-options-menu').classList.add('veui-split-button-menu');
            }
            menu.setAttribute('data-bulk-options-source', source);
            summary = menu.querySelector('summary');
            summary.setAttribute('data-base-label', menuLabel);
            summary.appendChild($('<span/>', {
                class: 'vec-bulk-selected-count',
                'data-role': 'bulk-selected-count',
                hidden: true
            })[0]);

            return menu;
        }

        function buildAutoMapAction() {
            var action = entityOptions.createAction({
                role: 'auto-map-categories',
                className: 'vec-auto-map-categories',
                iconClass: 'vec-auto-map-icon',
                label: $t('Auto Connect')
            });
            var icon = action.querySelector('.vec-auto-map-icon');
            var image = document.createElement('img');

            image.className = 'vec-auto-map-icon';
            image.src = String($root.attr('data-auto-match-icon-url') || '');
            image.alt = '';
            image.setAttribute('aria-hidden', 'true');
            icon.replaceWith(image);

            return action;
        }

        function buildEditConfigurationAction(label) {
            return entityOptions.createAction({
                role: 'open-edit-mapping',
                className: 'vec-edit-configuration',
                iconClass: 'veui-section-navigation-icon veui-section-navigation-icon-attribution-pen',
                label: $t('Edit mapping'),
                ariaLabel: $t('Edit mapping %1').replace('%1', label),
                attributes: {'aria-haspopup': 'dialog'}
            });
        }

        function buildRefreshAction() {
            var action = entityOptions.createAction({
                role: 'refresh-categories',
                className: 'vec-refresh-categories',
                iconClass: 'vec-icon vec-icon-refresh',
                label: $t('Refresh')
            });
            var icon = action.querySelector('.vec-icon-refresh');
            var image = document.createElement('img');

            image.className = 'vec-icon vec-icon-refresh';
            image.src = String($root.attr('data-refresh-icon-url') || '');
            image.alt = '';
            image.setAttribute('aria-hidden', 'true');
            icon.replaceWith(image);

            return action;
        }

        function buildBulkCategorySelection(source, identifier, label) {
            var selectionLabel = $t('Select category %1').replace('%1', label);

            return $('<label/>', {
                class: 'vec-bulk-category-select',
                title: selectionLabel
            }).attr('draggable', 'false').append(
                $('<input/>', {
                    type: 'checkbox',
                    'data-role': 'bulk-category-select',
                    'data-bulk-source': source,
                    'data-identifier': String(identifier),
                    'aria-label': selectionLabel
                }).prop('checked', !!bulkSelection[source][String(identifier)]),
                $('<span/>', {'aria-hidden': 'true'})
            );
        }

        function setBulkBranchSelected(source, identifier, checked) {
            var branch;
            var configuredRoot;

            if (source === 'ergo') {
                configuredRoot = getConfiguredRootMapping();
                if (configuredRoot && identifier === configuredRoot.key) {
                    branch = [configuredRoot.key].concat(categories.map(function (category) {
                        return category.code;
                    }));
                } else {
                    branch = subtreeMapping.branchIdentifiers(
                        categories,
                        identifier,
                        'code',
                        'source_parent_code'
                    );
                }
            } else if (source === 'magento') {
                branch = subtreeMapping.branchIdentifiers(
                    magentoCategories,
                    identifier,
                    'id',
                    'parent_id'
                );
            } else {
                return;
            }

            branch.forEach(function (branchIdentifier) {
                if (checked) {
                    bulkSelection[source][String(branchIdentifier)] = true;
                } else {
                    delete bulkSelection[source][String(branchIdentifier)];
                }
            });
        }

        function clearBulkSelection(source) {
            if (!bulkSelection[source]) {
                return;
            }

            bulkSelection[source] = {};
            $root.find('[data-role="bulk-category-select"][data-bulk-source="' + source + '"]')
                .prop('checked', false);
            updateBulkControls();
        }

        function unmapSelectedCategories(source) {
            var changed = false;

            if (!bulkSelection[source]) {
                return;
            }
            Object.keys(bulkSelection[source]).forEach(function (identifier) {
                var mappedCategory = source === 'ergo'
                    ? mappingIndex.find(identifier)
                    : mappingIndex.findByMagentoId(Number(identifier));

                if (mappedCategory && mappedCategory.magento_category_id
                    && mappingIndex.assign(mappedCategory.code, null)
                ) {
                    changed = true;
                }
            });
            bulkSelection[source] = {};
            if (changed) {
                markDirty();
            }
            renderAll();
        }

        function pruneBulkSelection() {
            var sourceCodes = {};
            var magentoIds = {};
            var configuredRoot = getConfiguredRootMapping();

            categories.forEach(function (category) {
                sourceCodes[category.code] = true;
            });
            if (configuredRoot) {
                sourceCodes[configuredRoot.key] = true;
            }
            magentoCategories.forEach(function (category) {
                magentoIds[String(Number(category.id))] = true;
            });
            Object.keys(bulkSelection.ergo).forEach(function (code) {
                if (!sourceCodes[code]) {
                    delete bulkSelection.ergo[code];
                }
            });
            Object.keys(bulkSelection.magento).forEach(function (id) {
                if (!magentoIds[id]) {
                    delete bulkSelection.magento[id];
                }
            });
        }

        function updateBulkControls() {
            pruneBulkSelection();
            ['ergo', 'magento'].forEach(function (source) {
                var count = Object.keys(bulkSelection[source]).length;
                var availability = bulkActions.resolve(
                    source,
                    Object.keys(bulkSelection[source]),
                    categories,
                    magentoCategories
                );
                var $menu = $root.find('[data-bulk-options-source="' + source + '"]');
                var $summary = $menu.children('summary');
                var baseLabel = String($summary.data('base-label') || '');

                $root.find('[data-role="bulk-category-select"][data-bulk-source="' + source + '"]')
                    .each(function () {
                        $(this).prop('checked', !!bulkSelection[source][String($(this).data('identifier') || '')]);
                    });
                $menu.find('[data-role="unmap-selected-categories"]')
                    .prop('disabled', !availability.disconnect);
                $menu.find('[data-role="bulk-selected-count"]')
                    .text(String(count))
                    .prop('hidden', count === 0);
                $summary.attr(
                    'aria-label',
                    count ? baseLabel + '. ' + $t('Selected: %1').replace('%1', String(count)) : baseLabel
                );
                syncVisibilityControl(source, $menu.find('[data-role="visibility-toggle"]')[0]);
                if (source === 'ergo') {
                    syncMappedVisibilityControl($menu.find('[data-role="mapped-visibility-toggle"]')[0]);
                }
            });
            $root.trigger('ergonode:category-mapping:selection-changed');
        }

        function syncVisibilityControl(source, button) {
            var items = source === 'ergo' ? categories : magentoCategories;
            var available = items.some(function (category) {
                return category.active === false;
            });

            if (!button) {
                return;
            }
            if (!available) {
                showBlocked[source] = false;
            }
            visibilityToggle.setVisible(button, available && showBlocked[source]);
            button.disabled = !available;
        }

        function syncMappedVisibilityControl(button) {
            var hint;

            if (!button) {
                return;
            }
            button.setAttribute('aria-pressed', hideMappedErgonode ? 'true' : 'false');
            hint = button.getAttribute(hideMappedErgonode ? 'data-show-hint' : 'data-hide-hint') || '';
            button.setAttribute('aria-label', hint);
            button.title = hint;
        }

        function initializeCollapsedNodes() {
            rebuildIndexes();

            var storedState = collapseStorage.load(categoryTreeId, browserStorage);
            var sourceBranches = {};
            var sourceDefaults = {};
            var magentoBranches = {};
            var magentoDefaults = {};
            var configuredRoot = getConfiguredRootMapping();

            categories.forEach(function (category) {
                if (getSourceChildren(category.code).length) {
                    sourceBranches[category.code] = true;
                    sourceDefaults[category.code] = true;
                }
            });
            magentoCategories.forEach(function (category) {
                if (getMagentoChildren(category.id).length) {
                    magentoBranches[String(category.id)] = true;
                    magentoDefaults[String(category.id)] = true;
                }
            });
            if (configuredRoot && getSourceRoots().length) {
                sourceBranches[configuredRoot.key] = true;
            }

            collapsedSourceNodes = storedState
                ? retainCollapsedNodes(storedState.source, sourceBranches)
                : sourceDefaults;
            collapsedMagentoNodes = storedState
                ? retainCollapsedNodes(storedState.magento, magentoBranches)
                : magentoDefaults;
        }

        function retainCollapsedNodes(storedNodes, availableBranches) {
            var retained = {};

            Object.keys(storedNodes || {}).forEach(function (key) {
                if (availableBranches[key] && storedNodes[key] === true) {
                    retained[key] = true;
                }
            });

            return retained;
        }

        function saveCollapsedNodes() {
            collapseStorage.save(categoryTreeId, {
                source: collapsedSourceNodes,
                magento: collapsedMagentoNodes
            }, browserStorage);
        }

        function getBrowserStorage() {
            try {
                return window.localStorage || null;
            } catch (error) {
                return null;
            }
        }

        function exposeExtensionApi() {
            element.veaCategoryMappingApi = {
                getConfig: function () { return config; },
                getDraft: mappingDraft,
                validateLayout: function () {
                    return post(config.urls.validate, {payload: JSON.stringify({
                        category_tree_id: categoryTreeId,
                        categories: categories
                    })});
                },
                isDirty: function () { return dirty; },
                isSourceBlocked: isSourceBlocked,
                hasUsableModels: hasUsableModels,
                replaceModels: replaceModels,
                applyConfig: applyCategoryTreeConfig,
                post: post,
                setBusy: setBusy,
                operationState: operationState,
                cleanup: scope.cleanup,
                setOperationPending: function (pending) { extensionOperationPending = !!pending; },
                applySubtreeAssignments: function (suggestions, codes) {
                    var stats = subtreeMapping.applyAssignments(categories, normalizeCategories(suggestions), codes);
                    renderAll();
                    return stats;
                },
                getCategories: function () {
                    return $.extend(true, [], categories);
                },
                getMagentoCategories: function () {
                    return $.extend(true, [], magentoCategories);
                },
                getCategoryTreeId: function () {
                    return categoryTreeId;
                },
                getBulkSelection: function (source) {
                    return Object.keys(bulkSelection[String(source || '')] || {});
                },
                clearBulkSelection: clearBulkSelection,
                addAndMapCategory: addAndMapCategory,
                batchUpdate: batchUpdate,
                removeCategory: removeCategory,
                setCategoryParent: setCategoryParent,
                saveLayout: function (excludedCodes, beforeRender) {
                    return saveLayout(Array.isArray(excludedCodes) ? excludedCodes : [], beforeRender);
                },
                setSaveRecoveryHandler: function (handler) {
                    saveRecoveryHandler = handler;
                },
                markCategoryRemotePrepared: markCategoryRemotePrepared,
                markCategoryPublished: markCategoryPublished,
                markDirty: markDirty,
                notify: showMessage,
                render: renderAll
            };
            $root.trigger('ergonode:category-mapping:ready', [element.veaCategoryMappingApi]);
        }

        function addAndMapCategory(category, magentoId) {
            var normalized = normalizeCategories([category])[0];
            var existing;
            var mappingCategory;
            var targetCategory;

            if (!normalized) {
                return false;
            }

            existing = mappingIndex.find(normalized.code);
            mappingCategory = existing || normalized;
            targetCategory = magentoTree.byId[Number(magentoId || 0)];

            if (!mappingCategory.active || !targetCategory || !targetCategory.active) {
                return false;
            }

            if (!existing) {
                categories.push(normalized);
                mappingIndex.add(normalized);
            } else if (existing.magento_category_id) {
                return false;
            }

            if (!mappingIndex.assign(normalized.code, targetCategory.id)) {
                return false;
            }

            markDirty();
            renderAll();

            return true;
        }

        function removeCategory(code) {
            code = String(code || '');
            if (!mappingState.remove(categories, code)) {
                return false;
            }

            mappingIndex = mappingState.createIndex(categories);
            delete persistedMappings[code];
            markDirty();
            renderAll();

            return true;
        }

        function setCategoryParent(code, parentCode) {
            var category = categories.filter(function (item) {
                return item.code === String(code || '');
            })[0];

            if (!category) {
                return false;
            }

            parentCode = parentCode ? String(parentCode) : null;
            if (category.parent_code === parentCode && category.source_parent_code === parentCode) {
                return true;
            }

            category.parent_code = parentCode;
            category.source_parent_code = parentCode;
            markDirty();
            renderAll();

            return true;
        }

        function renderErgonodeList() {
            if (isSourceBlocked()) {
                window.clearTimeout(searchTimers.ergo);
                return;
            }

            var query = normalize($ergoSearch.val());
            var view = treeView.project(sourceTree, {
                matches: function (category) {
                    return !query || searchableCategory(category).indexOf(query) !== -1;
                },
                isExcluded: function (category) {
                    return !category.active && !showBlocked.ergo;
                },
                isContext: function (category) {
                    return hideMappedErgonode && Boolean(category.magento_category_id);
                }
            });
            var $tree = $('<div/>', {class: 'vec-tree-children vec-source-tree'});
            var $rootChildren = $('<div/>', {class: 'vec-node-children vec-source-node-children'});
            var configuredRoot = getConfiguredRootMapping();
            var rootMatches = configuredRoot
                && (!query || normalize([configuredRoot.label, configuredRoot.code].join(' ')).indexOf(query) !== -1);
            var hasVisibleRootChildren = view.nodes.length > 0;
            var showConfiguredRoot = configuredRoot && (
                (!hideMappedErgonode && (rootMatches || hasVisibleRootChildren))
                || (hideMappedErgonode && hasVisibleRootChildren)
            );
            var rootExpanded = !showConfiguredRoot
                || treeState.isExpanded(collapsedSourceNodes, configuredRoot.key, !!query);
            var $rootNode;

            window.clearTimeout(searchTimers.ergo);
            if (rootExpanded) {
                view.nodes.forEach(function (node) {
                    appendErgonodeTreeNode($rootChildren, node, query);
                });
            }
            if (showConfiguredRoot) {
                $rootNode = $('<div/>', {
                    class: 'vec-tree-node vec-source-tree-node vec-configured-root-node',
                    'data-code': configuredRoot.code
                }).append(buildTreeNodeRow(
                    buildErgonodeRootCard(configuredRoot, hideMappedErgonode),
                    'source',
                    configuredRoot.key,
                    hasVisibleRootChildren,
                    rootExpanded
                ));
                if (hasVisibleRootChildren && rootExpanded) {
                    $rootNode.append($rootChildren);
                }
                $tree.append($rootNode);
            } else {
                $tree.append($rootChildren.children());
                if (!view.count) {
                    $tree.append(buildErgonodeEmptyState(query, configuredRoot));
                }
            }
            $ergoList.empty().append($tree);
            updateBulkControls();
        }

        function buildErgonodeEmptyState(query, configuredRoot) {
            var $emptyState = $('<div/>', {class: 'vec-empty-row'}).append(
                $('<span/>').text(getErgonodeEmptyMessage(query, configuredRoot))
            );
            var $mappedToggle;

            if (!isAllMappedEmptyState(query, configuredRoot)) {
                return $emptyState;
            }
            $mappedToggle = $('<button/>', {
                type: 'button',
                class: 'veui-button veui-visibility-control vec-empty-mapped-toggle',
                'data-role': 'mapped-visibility-toggle',
                'data-show-hint': $t('Show connected categories'),
                'data-hide-hint': $t('Hide connected categories'),
                'aria-pressed': 'true'
            }).append(
                $('<span/>', {class: 'veui-visibility-icon', 'aria-hidden': 'true'}),
                $('<span/>').text($t('Connected'))
            );
            syncMappedVisibilityControl($mappedToggle[0]);

            return $emptyState.append($mappedToggle);
        }

        function getErgonodeEmptyMessage(query, configuredRoot) {
            if (isAllMappedEmptyState(query, configuredRoot)) {
                return $t('No categories. All categories are already mapped.');
            }

            return $t('Brak kategorii.');
        }

        function isAllMappedEmptyState(query, configuredRoot) {
            var hasCategories = Boolean(configuredRoot) || categories.length > 0;
            var allCategoriesMapped = !categories.some(function (category) {
                return !category.magento_category_id;
            });

            return !query && hideMappedErgonode && hasCategories && allCategoriesMapped;
        }

        function renderMagentoList() {
            if (isSourceBlocked()) {
                window.clearTimeout(searchTimers.magento);
                return;
            }

            var query = normalize($magentoSearch.val());
            var view = treeView.project(magentoTree, {
                matches: function (category) {
                    return !query || searchableMagento(category, mappingsByMagentoId[category.id]).indexOf(query) !== -1;
                },
                isExcluded: function (category) {
                    return !category.active && !showBlocked.magento;
                }
            });
            var $tree = $('<div/>', {class: 'vec-tree-children vec-magento-tree'});

            window.clearTimeout(searchTimers.magento);
            view.nodes.forEach(function (node) {
                appendMagentoTreeNode($tree, node, query);
            });
            if (!view.count) {
                $tree.append($('<div/>', {class: 'vec-empty-row'}).text($t('Brak kategorii Magento.')));
            }
            $magentoList.empty().append($tree);
            $root.find('[data-role="magento-count"]').text(String(view.count));
            $root.find('[data-role="mapping-count"]').text(String(mappingState.count(categories)));
            updateBulkControls();
        }

        function appendErgonodeTreeNode($target, node, query) {
            var category = node.item;
            var hasVisibleChildren = node.children.length > 0;
            var expanded = treeState.isExpanded(collapsedSourceNodes, category.code, !!query);
            var $node = $('<div/>', {
                class: 'vec-tree-node vec-source-tree-node',
                'data-code': category.code
            }).append(buildTreeNodeRow(
                buildErgonodeCard(category, node.context),
                'source',
                category.code,
                hasVisibleChildren,
                expanded
            ));
            var $children;

            if (hasVisibleChildren && expanded) {
                $children = $('<div/>', {class: 'vec-node-children vec-source-node-children'});
                node.children.forEach(function (child) {
                    appendErgonodeTreeNode($children, child, query);
                });
                $node.append($children);
            }
            $target.append($node);
        }

        function appendMagentoTreeNode($target, node, query) {
            var category = node.item;
            var hasVisibleChildren = node.children.length > 0;
            var expanded = treeState.isExpanded(collapsedMagentoNodes, category.id, !!query);
            var $node = $('<div/>', {
                class: 'vec-tree-node vec-magento-tree-node',
                'data-magento-id': category.id
            }).append(buildTreeNodeRow(
                buildMagentoCard(category),
                'magento',
                category.id,
                hasVisibleChildren,
                expanded
            ));
            var $children;

            if (hasVisibleChildren && expanded) {
                $children = $('<div/>', {class: 'vec-node-children vec-magento-node-children'});
                node.children.forEach(function (child) {
                    appendMagentoTreeNode($children, child, query);
                });
                $node.append($children);
            }
            $target.append($node);
        }

        function buildTreeNodeRow($card, tree, key, hasChildren, expanded) {
            var label;
            var $toggle;

            if (hasChildren) {
                label = expanded ? $t('Zwiń dzieci') : $t('Rozwiń dzieci');
                $toggle = $('<button/>', {
                    type: 'button',
                    class: 'vec-tree-toggle',
                    title: label,
                    'aria-label': label,
                    'aria-expanded': expanded ? 'true' : 'false',
                    'data-role': 'toggle-children',
                    'data-tree': tree,
                    'data-node-key': String(key)
                }).append($('<span/>', {class: 'vec-tree-toggle-icon', 'aria-hidden': 'true'}));
            } else {
                $toggle = $('<span/>', {
                    class: 'vec-tree-toggle-spacer',
                    'aria-hidden': 'true'
                });
            }

            return $('<div/>', {class: 'vec-node-row'}).append($toggle, $card);
        }

        function buildMappingIndicator(className, label, code, statusLabel, statusMessage) {
            var hint = [label, code, statusLabel, statusMessage].filter(function (part) {
                return String(part || '').trim() !== '';
            }).join(' · ');
            var hintId = 'vec-mapping-hint-' + (++mappingHintSequence);
            var $hint = $('<span/>', {
                class: 'vec-mapping-hint',
                id: hintId,
                role: 'tooltip'
            }).append(
                $('<strong/>').text(label),
                $('<span/>').text(code)
            );

            if (statusLabel) {
                $hint.append($('<span/>', {class: 'vec-mapping-state-copy'}).text(statusLabel));
            }
            if (statusMessage) {
                $hint.append($('<span/>', {class: 'vec-mapping-error-copy'}).text(statusMessage));
            }

            return $('<button/>', {
                type: 'button',
                class: className + ' vec-mapping-indicator',
                'data-mapping-label': label,
                'data-mapping-code': code,
                'aria-label': hint,
                'aria-describedby': hintId
            }).attr('draggable', 'false').append(
                $('<span/>', {class: 'vec-mapping-status-icon veui-connected-icon', 'aria-hidden': 'true'}),
                $hint
            );
        }

        function buildPendingSaveIndicator() {
            var message = $t('Mapowanie oczekuje na zapis');
            var tooltipId = 'vec-pending-save-tooltip-' + (++pendingSaveHintSequence);

            return $('<button/>', {
                type: 'button',
                class: 'vec-pending-save',
                'data-role': 'pending-mapping-save',
                'aria-label': message,
                'aria-describedby': tooltipId
            }).attr('draggable', 'false').append(
                $('<span/>', {
                    class: 'vec-pending-save-icon',
                    'aria-hidden': 'true'
                }),
                $('<span/>', {
                    class: 'vec-pending-save-tooltip',
                    id: tooltipId,
                    role: 'tooltip'
                }).text(message)
            );
        }

        function buildExcludedCategoryInfo() {
            var title = $t('Category is excluded from mapping');
            var description = $t(
                'This category will not be used in mapping. Include it in its options to make it available again.'
            );
            var tooltipId = 'vec-excluded-category-hint-' + (++excludedCategoryHintSequence);

            return $('<span/>', {
                class: 'vec-configuration-disabled-info',
                tabindex: '0',
                role: 'img',
                'aria-label': title,
                'aria-describedby': tooltipId
            }).append(
                $('<span/>', {
                    class: 'vec-information-icon',
                    'aria-hidden': 'true'
                }).text('i'),
                $('<span/>', {
                    class: 'vec-mapping-hint vec-configuration-disabled-tooltip',
                    id: tooltipId,
                    role: 'tooltip'
                }).append(
                    $('<strong/>').text(title),
                    $('<span/>').text(description)
                )
            );
        }

        function hasPendingMappingRemoval(category) {
            var persistedMagentoId = Number(persistedMappings[category.code] || 0);

            return persistedMagentoId > 0
                && Number(category.magento_category_id || 0) !== persistedMagentoId;
        }

        function hasPendingMappingAddition(category) {
            return category && Number(category.magento_category_id || 0) > 0
                && Number(category.magento_category_id) !== Number(persistedMappings[category.code] || 0);
        }

        function findPendingMappingRemovalByMagentoId(magentoId) {
            magentoId = Number(magentoId || 0);

            return removalsByMagentoId[magentoId] || null;
        }

        function buildErgonodeCard(category, isMappedParent) {
            var magento = category.magento_category_id
                ? magentoTree.byId[category.magento_category_id]
                : null;
            var $card = $('<div/>', {
                class: 'vec-node-card vec-ergo-card has-bulk-selection' +
                    (magento ? ' is-mapped' : '') +
                    (isMappedParent ? ' is-mapped-parent' : '') +
                    (category.active ? '' : ' is-blocked'),
                'data-drag-type': 'category',
                'data-code': category.code,
                'data-drag-value': category.code
            }).append(
                buildBulkCategorySelection('ergo', category.code, category.label),
                $('<span/>', {class: 'vec-card-copy'}).append(
                    $('<strong/>').text(category.label),
                    $('<span/>').text(category.code)
                )
            );

            if (category.active) {
                nativeDrag.enableSource($card);
            }

            if (magento) {
                $card.append(
                    buildMappingIndicator('vec-source-mapping', magento.label, '#' + magento.id)
                );
            } else if (hasPendingMappingRemoval(category)) {
                $card.append(buildPendingSaveIndicator());
            }
            $card.append(buildErgonodeCategoryOptions(category));

            return $card;
        }

        function buildErgonodeCategoryOptions(category) {
            var actions = [{
                role: 'delete-snapshot-category',
                className: 'vec-delete-snapshot-category',
                iconClass: 'vec-delete-snapshot-icon',
                label: $t('Remove from list'),
                title: $t('Remove from list'),
                ariaLabel: $t('Remove from list'),
                attributes: {
                    'data-code': category.code,
                    'data-label': category.label
                }
            }];

            return buildCategoryOptions(
                category.label,
                actions,
                buildActiveToggle('ergo', category.code, category.active)
            );
        }

        function buildMagentoCategoryOptions(category, mappedCategory, isConfiguredRoot) {
            var actions = [];

            if (mappedCategory && !isConfiguredRoot) {
                actions.push({
                    role: 'unmap-category',
                    className: 'vec-unmap',
                    iconClass: 'vec-unmap-icon',
                    label: $t('Disconnect'),
                    attributes: {'data-code': mappedCategory.code}
                });
            }

            return buildCategoryOptions(
                category.label,
                actions,
                buildActiveToggle('magento', category.id, category.active)
            );
        }

        function buildCategoryOptions(categoryLabel, actions, toggle) {
            var menuLabel = $t('Category options') + ': ' + categoryLabel;

            return $(entityOptions.create({
                actions: actions,
                menuLabel: menuLabel,
                toggle: toggle
            }));
        }

        function buildErgonodeRootCard(configuredRoot, isMappedParent) {
            return $('<div/>', {
                class: 'vec-node-card vec-ergo-card vec-configured-root-card is-mapped has-bulk-selection' +
                    (isMappedParent ? ' is-mapped-parent' : '')
            }).append(
                buildBulkCategorySelection('ergo', configuredRoot.key, configuredRoot.label),
                $('<span/>', {class: 'vec-card-copy'}).append(
                    $('<strong/>').text(configuredRoot.label),
                    $('<span/>').text(configuredRoot.code)
                ),
                buildMappingIndicator(
                    'vec-source-mapping',
                    configuredRoot.magentoLabel,
                    '#' + configuredRoot.magentoId
                )
            );
        }

        function buildMagentoCard(category) {
            var configuredRoot = getConfiguredRootMapping();
            var isConfiguredRoot = configuredRoot && configuredRoot.magentoId === category.id;
            var mappedCategory = isConfiguredRoot
                ? configuredRoot
                : mappingsByMagentoId[category.id] || null;
            var pendingMappingRemoval = !mappedCategory
                ? findPendingMappingRemovalByMagentoId(category.id)
                : null;
            var mappingPresentation = getMagentoMappingPresentation(category, mappedCategory);
            var $mapping;
            var attributes = {
                class: 'vec-node-card vec-magento-card has-bulk-selection' +
                    (category.active ? '' : ' is-blocked') +
                    (category.active ? '' : ' is-disabled') +
                    (isConfiguredRoot ? ' is-configured-root' : '') +
                    (mappingPresentation ? ' is-mapped is-mapping-' + mappingPresentation.state : '') +
                    (mappingPresentation && mappingPresentation.state === 'error'
                        ? ' has-mapping-error'
                        : ''),
                'data-magento-id': category.id
            };

            if (mappingPresentation) {
                attributes['data-mapping-state'] = mappingPresentation.state;
            }
            if (!category.active) {
                $mapping = buildExcludedCategoryInfo();
            } else if (pendingMappingRemoval || hasPendingMappingAddition(mappedCategory)) {
                $mapping = buildPendingSaveIndicator();
            } else if (mappedCategory) {
                $mapping = buildMappingIndicator(
                    'vec-magento-mapping is-mapped',
                    mappedCategory.label,
                    mappedCategory.code,
                    mappingPresentation.label,
                    mappingPresentation.message
                );
            } else {
                $mapping = $('<span/>', {
                    class: 'vec-magento-mapping is-empty',
                    title: $t('Upuść kategorię Ergonode, aby ją zmapować')
                }).append(
                    $('<span/>', {class: 'vec-visually-hidden'}).text(
                        $t('Upuść kategorię Ergonode, aby ją zmapować')
                    )
                );
            }
            if (category.active && !isConfiguredRoot && !mappedCategory) {
                attributes['data-drop-zone'] = 'magento-target';
            }

            return $('<div/>', attributes).append(
                buildBulkCategorySelection('magento', category.id, category.label),
                $('<span/>', {class: 'vec-card-copy'}).append(
                    $('<span/>', {class: 'vec-card-label'}).text(category.label),
                    buildMagentoCategoryPath(category.path, category.id)
                ),
                $mapping,
                buildMagentoCategoryOptions(category, mappedCategory, isConfiguredRoot)
            );
        }

        function buildMagentoCategoryPath(path, fallbackIdentifier) {
            var segments = String(path || '').split('/').filter(Boolean);
            var current;
            var prefix;

            if (!segments.length) {
                segments.push(String(fallbackIdentifier || ''));
            }
            current = segments.pop();
            prefix = '/' + (segments.length ? segments.join('/') + '/' : '');

            return $('<span/>', {
                class: 'vec-card-path',
                title: prefix + current
            }).text(prefix).append(
                $('<strong/>', {class: 'vec-card-path-current'}).text(current)
            );
        }

        function getMagentoMappingPresentation(category, mappedCategory) {
            if (!mappedCategory) {
                return null;
            }
            if (hasPendingMappingAddition(mappedCategory)) {
                return {
                    state: 'pending',
                    label: $t('Mapowanie oczekuje na zapis'),
                    message: ''
                };
            }
            if (String(mappedCategory.sync_status || '').toLowerCase() === 'error') {
                return {
                    state: 'error',
                    label: $t('Mapping error'),
                    message: String(mappedCategory.sync_message || '')
                };
            }
            if (!category.active || mappedCategory.active === false) {
                return {
                    state: 'disabled',
                    label: $t('Mapping disabled'),
                    message: ''
                };
            }

            return {
                state: 'active',
                label: $t('This element is already mapped.'),
                message: ''
            };
        }

        function getConfiguredRootMapping() {
            var current = config.current_category_tree || {};
            var code = String(current.tree_code || config.tree_code || '');
            var magentoId = Number(current.root_category_id || config.magento_root_id || 0);

            if (!code || magentoId <= 0) {
                return null;
            }

            return {
                key: '__configured_root__:' + code,
                code: code,
                label: String(current.tree_label || code),
                magentoId: magentoId,
                magentoLabel: String(current.root_category_label || $t('Magento root')),
                active: current.is_active !== false,
                sync_status: ''
            };
        }

        function buildActiveToggle(source, identifier, active) {
            var label = active ? $t('Exclude') : $t('Include');
            var $button = $('<button/>', {
                type: 'button',
                class: 'veui-visibility-control',
                title: label,
                'aria-label': label,
                'aria-pressed': active ? 'true' : 'false',
                'data-role': 'category-active-toggle',
                'data-source': source,
                'data-identifier': String(identifier)
            }).attr('draggable', 'false').append(
                $('<span/>', {class: 'veui-visibility-icon', 'aria-hidden': 'true'})
            );

            return $button[0];
        }

        function toggleCategoryActive(source, identifier) {
            var item;

            if (source === 'ergo') {
                item = categories.filter(function (category) {
                    return category.code === identifier;
                })[0];
                if (!item) {
                    return;
                }
                subtreeMapping.setBranchActive(categories, identifier, !item.active);
            } else if (source === 'magento') {
                item = mappingState.findCategoryById(magentoCategories, identifier);
                if (!item) {
                    return;
                }
                item.active = !item.active;
            } else {
                return;
            }

            markDirty();
            renderAll();
        }

        function deleteSnapshotCategory(code, $button) {
            if (!code) {
                return;
            }
            if (dirty) {
                showMessage('error', $t('Save mapping changes before removing this category.'));
                return;
            }

            setBusy($button, true);
            post(config.urls.delete_snapshot, {
                category_tree_id: categoryTreeId,
                ergonode_code: code
            }).done(function (response) {
                if (!response || !response.success) {
                    showMessage('error', response && response.message
                        ? response.message
                        : $t('Unable to remove the category from this list.'));
                    return;
                }

                replaceModels(response, true);
                dirty = false;
                updateButtons();
                showMessage('success', response.message || $t('Category has been removed from this list.'));
                $root.trigger('ergonode:category-mapping:snapshot-removed', [response]);
            }).fail(function () {
                showMessage('error', $t('Unable to remove the category from this list.'));
            }).always(function () {
                setBusy($button, false);
            });
        }

        function mappingDraft() {
            return {
                category_tree_id: categoryTreeId,
                draft_mappings: categories.filter(function (category) {
                    return category.magento_category_id
                        && persistedMappings[category.code] !== category.magento_category_id;
                }).map(function (category) {
                    return {
                        ergonode_code: category.code,
                        magento_category_id: category.magento_category_id
                    };
                }),
                draft_visibility: collectVisibility()
            };
        }

        function previewCategories($button, successMessage, scopeCodes) {
            if (!config.urls.auto_map || isSourceBlocked() || !operationState.start()) {
                return;
            }
            setBusy($button, true);
            post(config.urls.auto_map, {
                payload: JSON.stringify(mappingDraft()),
                category_tree_id: categoryTreeId
            }).then(function (response) {
                if (!response || !response.success || !hasUsableModels(response)) {
                    showMessage('error', response && response.message
                        ? response.message
                        : $t('Unable to reconcile categories.'));
                    return;
                }
                if (scopeCodes && scopeCodes.length) {
                    var stats = subtreeMapping.applyAssignments(
                        categories, normalizeCategories(response.categories || []), scopeCodes
                    );
                    renderAll();
                    showMessage('success', [
                        successMessage,
                        $t('Mapped children') + ': ' + stats.mapped,
                        $t('Unmatched children') + ': ' + stats.unmatched
                    ].join(' '));
                } else {
                    replaceModels(response);
                    showMessage('success', formatAutoMapStats(successMessage, response.stats, response.conflicts));
                }
                markDirty();
            }).fail(function () {
                showMessage('error', $t('Unable to reconcile categories.'));
            }).always(function () {
                setBusy($button, false);
                operationState.finish();
            });
        }

        function formatAutoMapStats(message, stats, conflicts) {
            if (!stats) {
                return message || '';
            }
            return [
                message || '',
                $t('Database') + ': ' + Number(stats.database || 0),
                $t('Draft') + ': ' + Number(stats.draft || 0),
                $t('Name') + ': ' + Number(stats.name || 0),
                $t('Unmatched') + ': ' + Number(stats.unmatched || 0),
                $t('Created') + ': ' + Number(stats.created || 0),
                $t('Moved') + ': ' + Number(stats.moved || 0),
                $t('Conflicts') + ': ' + Number((conflicts || []).length)
            ].join(' ');
        }

        function replaceModels(response, assumePersisted) {
            categories = normalizeCategories(response.categories || []);
            magentoCategories = normalizeMagentoCategories(response.magento_categories || []);
            persistedMappings = collectPersistedMappings(categories, !!assumePersisted);
            bulkSelection = {ergo: {}, magento: {}};
            initializeCollapsedNodes();
            renderAll();
        }

        function hasUsableModels(response) {
            if (!response
                || !Array.isArray(response.categories)
                || !Array.isArray(response.magento_categories)
            ) {
                return false;
            }

            return response.categories.length > 0
                || response.magento_categories.length > 0
                || (categories.length === 0 && magentoCategories.length === 0);
        }

        function collectPersistedMappings(items, assumePersisted) {
            var mappings = {};

            items.forEach(function (category) {
                if (!category.magento_category_id) {
                    return;
                }
                if (assumePersisted
                    || category.mapping_source === 'database'
                    || category.mapping_source === 'excluded'
                ) {
                    mappings[category.code] = category.magento_category_id;
                }
            });

            return mappings;
        }

        function mapDroppedCategory(code, magentoId) {
            var descendants;

            if (!setMapping(code, magentoId)) {
                return;
            }

            descendants = subtreeMapping.descendants(categories, code);
            if (!descendants.length) {
                return;
            }

            if (config.urls.auto_map) {
                previewCategories($autoMapButton, $t('Child category auto-mapping has finished.'), descendants);
            }
        }

        function setMapping(code, magentoId) {
            var sourceCategory = mappingIndex.find(code);
            var targetCategory = magentoTree.byId[Number(magentoId || 0)];

            if (!sourceCategory || (magentoId && (!sourceCategory.active || !targetCategory || !targetCategory.active))) {
                return false;
            }
            if (!mappingIndex.assign(code, magentoId)) {
                return false;
            }

            markDirty();
            renderAll();

            return true;
        }

        function saveLayout(excludedCodes, beforeRender) {
            var excluded = Object.create(null);
            var payload;
            var request;
            var previousMappings = persistedMappings;

            if (pendingSave) {
                return pendingSave;
            }
            if (!Array.isArray(excludedCodes)) {
                excludedCodes = [];
            }
            excludedCodes.forEach(function (code) {
                excluded[String(code || '')] = true;
            });
            payload = {
                category_tree_id: categoryTreeId,
                categories: categories.filter(function (category) {
                    return !excluded[category.code];
                }).map(function (category) {
                    return {
                        code: category.code,
                        parent_code: category.parent_code,
                        sort_order: category.sort_order,
                        magento_category_id: category.magento_category_id,
                        extension_data: $.extend(true, {}, category.extension_data || {})
                    };
                }),
                visibility: collectVisibility()
            };

            if (!operationState.start('save')) {
                return $.Deferred().reject().promise();
            }
            setBusy($saveButton, true);
            try {
                request = saveWithRecovery(function () {
                    return post(config.urls.save, {payload: JSON.stringify(payload), category_tree_id: categoryTreeId});
                }, saveRecoveryHandler).then(function (response) {
                    if (!response || !response.success) {
                        showMessage('error', response && response.message ? response.message : $t('Nie udało się zapisać drzewa kategorii.'));
                        return response;
                    }

                    if (typeof beforeRender === 'function') {
                        beforeRender(response);
                    }
                    persistedMappings = collectPersistedMappings(categories.filter(function (category) {
                        return !excluded[category.code];
                    }), true);
                    renderAll();
                    dirty = false;
                    updateButtons();
                    showMessage('success', response.message || $t('Zapisano drzewo kategorii.'));
                    $root.trigger('ergonode:category-mapping:saved', [response]);
                    return response;
                });
            } catch (exception) {
                request = $.Deferred().reject(exception).promise();
            }
            pendingSave = request;
            request
                .fail(function () {
                    persistedMappings = previousMappings;
                    showMessage('error', $t('Nie udało się zapisać drzewa kategorii.'));
                })
                .always(function () {
                    setBusy($saveButton, false);
                    operationState.finish();
                    pendingSave = null;
                });

            return request;
        }

        function markCategoryRemotePrepared(code, remoteId) {
            var category = categories.filter(function (item) {
                return item.code === String(code || '');
            })[0];
            var extension;

            if (!category || !remoteId) {
                return false;
            }
            extension = category.extension_data.to_ergonode || {};
            extension.pending_create = !!extension.pending_create;
            extension.remote_prepared = true;
            category.extension_data.to_ergonode = extension;
            category.ergonode_category_id = String(remoteId);

            return true;
        }

        function markCategoryPublished(code) {
            var category = categories.filter(function (item) {
                return item.code === String(code || '');
            })[0];
            var extension;

            if (!category) {
                return false;
            }
            extension = category.extension_data.to_ergonode || {};
            delete extension.pending_create;
            delete extension.remote_prepared;
            category.extension_data.to_ergonode = extension;
            category.source_parent_code = category.parent_code;
            category.source_sort_order = category.sort_order;

            return true;
        }

        function collectVisibility() {
            return categories.map(function (category) {
                return {
                    source: 'ergo',
                    identifier: category.code,
                    active: category.active
                };
            }).concat(magentoCategories.map(function (category) {
                return {
                    source: 'magento',
                    identifier: String(category.id),
                    active: category.active
                };
            }));
        }

        function updateSourceIssues(response) {
            if (response.source_issues) {
                config.source_issues = response.source_issues;
            }
            if (response.category_trees) {
                config.category_trees = response.category_trees;
            }
            if (renderSourceIssues) {
                renderSourceIssues(config.source_issues || {}, config.category_trees || [],
                    config.current_category_tree || {}, config.source_permissions || {});
            }
        }

        function disableSourceMapping() {
            var form;

            if (extensionOperationPending || sourceRefreshPending || pendingSave || element.querySelector('.vec-layout[inert]')) {
                return;
            }
            if (!window.confirm($t('Wyłączyć to powiązanie? Kategorie i mapowania Magento zostaną zachowane. Niezapisane zmiany na ekranie zostaną odrzucone.'))) {
                return;
            }
            form = document.createElement('form');
            form.method = 'post';
            form.action = config.urls.settings_save;
            Object.entries({form_key: config.form_key, category_tree_id: categoryTreeId, is_active: '0'}).forEach(function (entry) {
                var input = document.createElement('input');

                input.type = 'hidden';
                input.name = entry[0];
                input.value = String(entry[1]);
                form.appendChild(input);
            });
            dirty = false;
            document.body.appendChild(form);
            form.submit();
        }

        function refreshCategories($button) {
            var requestedTreeId = categoryTreeId;

            if (extensionOperationPending || sourceRefreshPending || pendingSave || $button.prop('disabled') ||
                element.querySelector('.vec-layout[inert]')) {
                return;
            }
            if (dirty && !window.confirm($t('Masz niezapisane zmiany. Odświeżyć dane i je odrzucić?'))) {
                return;
            }

            sourceRefreshPending = true;
            setBusy($button, true);
            post(config.urls.refresh, {category_tree_id: categoryTreeId})
                .done(function (response) {
                    if (requestedTreeId !== categoryTreeId) {
                        return;
                    }
                    updateSourceIssues(response || {});
                    updateButtons();
                    if (!response || !response.success) {
                        showMessage('error', response && response.message
                            ? response.message
                            : $t('Nie udało się odświeżyć danych kategorii.'));
                        return;
                    }
                    if (!hasUsableModels(response)) {
                        showMessage('error', $t('Nie udało się odświeżyć danych kategorii.'));
                        return;
                    }

                    replaceModels(response, true);
                    dirty = false;
                    updateButtons();
                    showMessage('success', response.message || $t('Dane kategorii zostały odświeżone.'));
                    $root.trigger('ergonode:category-mapping:refreshed');
                })
                .fail(function () {
                    if (requestedTreeId !== categoryTreeId) {
                        return;
                    }
                    showMessage('error', $t('Nie udało się odświeżyć danych kategorii.'));
                })
                .always(function () {
                    sourceRefreshPending = false;
                    setBusy($button, false);
                });
        }

        function post(url, data, timeout) {
            data = data || {};
            data.form_key = config.form_key;

            return $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                timeout: typeof timeout === 'number' ? timeout : 60000,
                data: data
            });
        }

        function getSourceRoots() {
            return sourceTree.roots;
        }

        function getSourceChildren(parentCode) {
            return sourceTree.children[String(parentCode || '')] || [];
        }

        function getMagentoChildren(parentId) {
            return magentoTree.children[String(Number(parentId || 0))] || [];
        }

        function searchableCategory(category) {
            return normalize([category.label, category.code, category.sync_status].join(' '));
        }

        function searchableMagento(category, mappedCategory) {
            return normalize([
                category.label,
                category.id,
                category.path,
                category.url_key,
                mappedCategory ? mappedCategory.label : '',
                mappedCategory ? mappedCategory.code : ''
            ].join(' '));
        }

        function sortMagento(a, b) {
            return [a.level, a.position, a.label].join('|').localeCompare([b.level, b.position, b.label].join('|'));
        }

        function sortBySourceOrder(a, b) {
            return [Number(a.source_sort_order || 0), normalize(a.label), a.code]
                .join('|')
                .localeCompare([Number(b.source_sort_order || 0), normalize(b.label), b.code].join('|'));
        }

        function normalize(value) {
            return (value || '').toString().trim().toLowerCase();
        }

        function markDirty() {
            dirty = true;
            if (!batchDepth) {
                updateButtons();
            }
        }

        function isSourceBlocked() {
            return !!config.source_issues && (config.source_issues.requires_refresh ||
                ['missing', 'unavailable'].indexOf(config.source_issues.status) !== -1);
        }

        function updateButtons() {
            var blocked = isSourceBlocked();

            $saveButton.prop('disabled', !dirty || blocked);
            $autoMapButton.prop('disabled', !!blocked);
        }

        function updateCounts() {
            $root.find('[data-role="mapping-count"]').text(String(mappingState.count(categories)));
        }

        function setBusy($button, busy) {
            $button.prop('disabled', busy || ($button.is($saveButton) && !dirty));
            $button.toggleClass('is-working', !!busy);
        }

    };
});
