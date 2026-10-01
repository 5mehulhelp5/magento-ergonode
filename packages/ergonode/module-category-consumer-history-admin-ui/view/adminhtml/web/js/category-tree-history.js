define([
    'jquery',
    'mage/translate',
    'Ergonode_CoreAdminUi/js/workspace',
    'Ergonode_CoreAdminUi/js/entity-options',
    'Ergonode_CategoryConsumerHistoryAdminUi/js/category-tree-history-state',
    'Ergonode_CategoryConsumerHistoryAdminUi/js/category-tree-operations'
], function ($, $t, workspace, entityOptions, historyState, operationsView) {
    'use strict';

    var operationLabel = operationsView.operationLabel;
    var originLabel = operationsView.originLabel;
    var formatDate = operationsView.formatDate;
    var safeToken = operationsView.safeToken;

    var actionLabels = {
        created: 'Created',
        deleted: 'Deleted',
        moved: 'Moved',
        reordered: 'Reordered',
        renamed: 'Renamed',
        included: 'Included',
        excluded: 'Excluded',
        connected: 'Connected',
        disconnected: 'Disconnected',
        reconnected: 'Remapped',
        source_moved: 'Moved in Ergonode',
        source_reordered: 'Reordered in Ergonode'
    };

    return function (config, element) {
        var root = element;
        var state = config.state || null;
        var request = null;
        var requestVersion = 0;
        var changesOnly = !state || state.changes_only !== false;
        var changesToggle = root.querySelector('[data-role="changes-only"]');
        var operationsRequest = null;
        var operations = Array.isArray(config.operations) ? config.operations.slice() : [];
        var operationsPagination = config.operations_pagination || {};
        var operationList = root.querySelector('[data-role="operation-list"]');
        var operationDetails = root.querySelector('[data-role="operation-details"]');
        var detailsDialog = root.querySelector('[data-role="details-dialog"]');
        var selectedChange = pickDefaultChange(state && state.changes || []);
        var collapsedNodes = Object.create(null);
        var changeHintSequence = 0;
        var hideConnected = true;
        var connectedToggle;
        var scope = workspace.mount(root);

        changesToggle.checked = changesOnly;
        scope.listen(changesToggle, 'change', function () {
            loadState(state && state.operation && state.operation.operation_id);
        });
        scope.cleanup(function () {
            requestVersion++;
            if (request && request.abort) { request.abort(); }
        });
        bindSourceOptions();
        bindTreePicker();
        bindDetailsDialog();
        bindSearch();
        renderOperations();
        renderState();
        if (config.open_details && state && state.operation) {
            openDetails(Number(state.operation.operation_id));
        }

        return scope;

        function bindSourceOptions() {
            var slot = root.querySelector('[data-role="source-options"]');
            var menu;

            if (!slot) {
                return;
            }
            menu = entityOptions.create({
                menuLabel: $t('Category actions: %1').replace('%1', 'Ergonode'),
                actions: [{
                    role: 'mapped-visibility-toggle',
                    className: 'veui-visibility-control',
                    iconClass: 'vec-connected-icon veui-connected-icon',
                    label: $t('Connected')
                }]
            });
            menu.classList.add('vec-bulk-options');
            connectedToggle = menu.querySelector('[data-role="mapped-visibility-toggle"]');
            slot.append(menu);
            syncConnectedToggle();
            entityOptions.bind(scope, root);
            scope.listen(connectedToggle, 'click', function () {
                hideConnected = !hideConnected;
                syncConnectedToggle();
                applySearch();
                menu.querySelector('summary').focus();
            });
        }

        function syncConnectedToggle() {
            var hint = hideConnected ? $t('Show connected categories') : $t('Hide connected categories');

            if (connectedToggle) {
                connectedToggle.setAttribute('aria-pressed', hideConnected ? 'true' : 'false');
                connectedToggle.setAttribute('aria-label', hint);
                connectedToggle.title = hint;
            }
        }

        function bindTreePicker() {
            var picker = root.querySelector('[data-role="tree-picker"]');

            if (!picker) {
                return;
            }
            picker.addEventListener('change', function () {
                var option = picker.options[picker.selectedIndex];

                if (option && option.dataset.url) {
                    window.location.assign(option.dataset.url);
                }
            });
        }

        function bindDetailsDialog() {
            var close = root.querySelector('[data-role="close-details"]');

            if (close && detailsDialog) {
                close.addEventListener('click', function () {
                    detailsDialog.close();
                });
            }
        }

        function openDetails(operationId) {
            function open() {
                var trigger = operationList.querySelector('[data-details-operation-id="' + operationId + '"]');

                renderDetails();
                if (trigger) {
                    trigger.focus();
                }
                detailsDialog.showModal();
            }

            if (state && state.operation && Number(state.operation.operation_id) === operationId) {
                open();
            } else {
                loadState(operationId, open);
            }
        }

        function bindSearch() {
            root.querySelectorAll('[data-role="tree-search"]').forEach(function (search) {
                search.addEventListener('input', applySearch);
            });
        }

        function renderOperations() {
            if (!operationList) {
                return;
            }
            operationsView.render(operationList, {
                operations: operations,
                changesOnly: changesOnly,
                pagination: operationsPagination,
                selectedOperation: config.selected_operation,
                selectedOperationId: state && state.operation && state.operation.operation_id,
                currentUrl: config.urls.mapping,
                onSelect: loadState,
                onDetails: openDetails,
                onLoadOlder: loadOlderOperations,
                loading: Boolean(operationsRequest)
            });
        }

        function createChangeList(changes) {
            var list = document.createElement('div');

            list.className = 'vech-change-list';
            list.setAttribute('aria-label', $t('Changes in selected operation'));
            if (!changes.length) {
                var unchanged = document.createElement('span');

                unchanged.className = 'vech-no-changes';
                unchanged.textContent = $t('The operation did not change this tree.');
                list.append(unchanged);
                return list;
            }
            changes.forEach(function (change) {
                var button = document.createElement('button');
                var label = document.createElement('strong');
                var actions = document.createElement('span');
                var snapshot = change.after || change.before || {};

                button.type = 'button';
                button.className = 'vech-change-link';
                button.classList.toggle('is-selected', isSameChange(change, selectedChange));
                label.textContent = snapshot.label || change.entity_identifier;
                actions.textContent = (change.actions || []).map(actionLabel).join(', ');
                button.append(label, actions);
                button.addEventListener('click', function () {
                    selectedChange = change;
                    renderDetails();
                    operationDetails.querySelector('.vech-change-link.is-selected').focus();
                });
                list.append(button);
            });

            return list;
        }

        function loadState(operationId, onLoaded) {
            var version = ++requestVersion;
            var requestedMode = changesToggle.checked;
            var data = {category_tree_id: Number(config.category_tree_id), changes_only: requestedMode ? 1 : 0};

            if (operationId) {
                data.operation_id = operationId;
            }
            if (request && request.abort) {
                request.abort();
            }
            root.classList.add('is-loading');
            request = $.ajax({
                url: config.urls.state,
                data: data,
                dataType: 'json',
                method: 'GET'
            }).done(function (response) {
                if (version !== requestVersion || scope.isDestroyed()) { return; }
                if (!response || !response.success) {
                    changesToggle.checked = changesOnly;
                    showMessage(response && response.message ? response.message : $t('History could not be loaded.'));
                    return;
                }
                state = response.state;
                changesOnly = requestedMode;
                changesToggle.checked = changesOnly;
                collapsedNodes = Object.create(null);
                selectedChange = pickDefaultChange(state && state.changes || []);
                hideMessage();
                renderOperations();
                renderState();
                if (onLoaded) {
                    onLoaded();
                }
            }).fail(function (xhr, status) {
                if (status === 'abort' || version !== requestVersion || scope.isDestroyed()) {
                    return;
                }
                changesToggle.checked = changesOnly;
                var response = xhr && xhr.responseJSON ? xhr.responseJSON : {};

                showMessage(response.message || $t('History could not be loaded.'));
            }).always(function () {
                if (version !== requestVersion || scope.isDestroyed()) { return; }
                root.classList.remove('is-loading');
                request = null;
            });
        }

        function loadOlderOperations(event) {
            var beforeOperationId = Number(operationsPagination.next_before_id || 0);
            var operationIds = operations.map(function (operation) {
                return Number(operation.operation_id);
            });

            if (operationsRequest || !beforeOperationId || !config.urls.operations) {
                return;
            }
            if (event && event.currentTarget) {
                event.currentTarget.disabled = true;
                event.currentTarget.textContent = $t('Loading...');
            }
            operationsRequest = $.ajax({
                url: config.urls.operations,
                data: {
                    category_tree_id: Number(config.category_tree_id),
                    before_operation_id: beforeOperationId
                },
                dataType: 'json',
                method: 'GET'
            }).done(function (response) {
                var page;

                if (!response || !response.success || !response.page) {
                    showMessage(response && response.message
                        ? response.message
                        : $t('Older history operations could not be loaded.'));
                    return;
                }
                page = response.page;
                (page.items || []).forEach(function (operation) {
                    if (operationIds.indexOf(Number(operation.operation_id)) === -1) {
                        operations.push(operation);
                        operationIds.push(Number(operation.operation_id));
                    }
                });
                operationsPagination = {
                    total: Number(page.total || operations.length),
                    page_size: Number(page.page_size || operationsPagination.page_size || 10),
                    has_more: Boolean(page.has_more),
                    next_before_id: page.next_before_id || null
                };
                hideMessage();
            }).fail(function (xhr) {
                var response = xhr && xhr.responseJSON ? xhr.responseJSON : {};

                showMessage(response.message || $t('Older history operations could not be loaded.'));
            }).always(function () {
                operationsRequest = null;
                renderOperations();
            });
        }

        function renderState() {
            renderTree();
            renderDetails();
        }

        function renderDetails() {
            var operation;
            var changes;
            var changeHeading;

            if (!operationDetails) {
                return;
            }
            operationDetails.replaceChildren();
            if (!state || !state.operation) {
                operationDetails.append(createDetailsEmpty());
                return;
            }
            changes = state.changes || [];
            operation = findSelectedOperation() || state.operation;
            operationDetails.append(createOperationSummary(operation, changes));
            if (selectedChange) {
                operationDetails.append(createChangeDetail(selectedChange));
            }
            changeHeading = document.createElement('div');
            changeHeading.className = 'vech-details-section-title';
            changeHeading.innerHTML = '<strong></strong><span></span>';
            changeHeading.firstElementChild.textContent = $t('Changes in selected operation');
            changeHeading.lastElementChild.textContent = String(changes.length);
            operationDetails.append(changeHeading, createChangeList(changes));
        }

        function createDetailsEmpty() {
            var empty = document.createElement('div');

            empty.className = 'vech-details-empty';
            empty.textContent = $t('Choose a historical operation to inspect its changes.');

            return empty;
        }

        function createOperationSummary(operation, changes) {
            var summary = document.createElement('section');
            var top = document.createElement('div');
            var title = document.createElement('strong');
            var status = document.createElement('span');
            var metrics = document.createElement('div');
            var metadata = document.createElement('dl');
            var categoryCount = operation.summary && typeof operation.summary.categories !== 'undefined'
                ? operation.summary.categories
                : uniqueCategoryCount(changes);

            summary.className = 'vech-summary';
            top.className = 'vech-summary-top';
            title.textContent = operationLabel(operation.operation_code);
            status.className = 'vech-status vech-status-' + safeToken(operation.status);
            status.textContent = $t(operation.status || 'success');
            top.append(title, status);
            metrics.className = 'vech-summary-grid';
            metrics.append(
                createMetric($t('Changes'), changes.length),
                createMetric($t('Categories'), categoryCount),
                createMetric($t('Structure'), countChanges(changes, [
                    'created', 'deleted', 'moved', 'reordered', 'source_moved', 'source_reordered'
                ])),
                createMetric($t('Mappings'), countChanges(changes, [
                    'connected', 'disconnected', 'reconnected'
                ]))
            );
            metadata.className = 'vech-summary-meta';
            appendMetadata(metadata, $t('Source'), originLabel(operation.origin));
            appendMetadata(metadata, $t('Started'), formatDate(operation.started_at));
            appendMetadata(metadata, $t('Actor'), operation.actor_name || $t('system'));
            summary.append(top, metrics, metadata);

            return summary;
        }

        function createMetric(label, value) {
            var metric = document.createElement('span');
            var count = document.createElement('strong');
            var name = document.createElement('small');

            metric.className = 'vech-summary-metric';
            count.textContent = String(value || 0);
            name.textContent = label;
            metric.append(count, name);

            return metric;
        }

        function appendMetadata(list, label, value) {
            var term = document.createElement('dt');
            var description = document.createElement('dd');

            term.textContent = label;
            description.textContent = displayValue(value);
            list.append(term, description);
        }

        function createChangeDetail(change) {
            var detail = document.createElement('section');
            var eyebrow = document.createElement('span');
            var title = document.createElement('strong');
            var badges = document.createElement('span');
            var lines = document.createElement('div');
            var button = document.createElement('button');
            var snapshot = change.after || change.before || {};

            detail.className = 'vech-change-detail';
            eyebrow.className = 'vech-detail-eyebrow';
            eyebrow.textContent = $t('Selected change');
            title.className = 'vech-detail-title';
            title.textContent = snapshot.label || change.entity_identifier;
            badges.className = 'vech-detail-badges';
            (change.actions || []).forEach(function (action) {
                var badge = document.createElement('span');

                badge.className = 'vech-badge vech-badge-' + actionGroup(action);
                badge.textContent = actionLabel(action);
                badges.append(badge);
            });
            lines.className = 'vech-detail-lines';
            describeChange(change, null).forEach(function (line) {
                var text = document.createElement('span');

                text.textContent = line;
                lines.append(text);
            });
            button.type = 'button';
            button.className = 'vech-show-on-tree';
            button.textContent = $t('Show on tree');
            button.addEventListener('click', function () {
                detailsDialog.close();
                focusChange(change);
            });
            detail.append(eyebrow, title, badges, lines, button);

            return detail;
        }

        function renderTree() {
            var trees;

            if (!state) {
                return;
            }
            trees = {
                target: historyState.buildMagentoTree(state.source || [], state.target || [], state.changes || []),
                source: historyState.buildErgonodeTree(state.source || [], state.changes || [], state.tree)
            };
            root.querySelectorAll('[data-role="tree"]').forEach(function (tree) {
                tree.replaceChildren();
                (changesOnly && !state.changes.length ? [] : trees[tree.dataset.side]).forEach(function (node) {
                    tree.append(createNode(node, tree.dataset.side));
                });
                var empty = document.createElement('span');

                empty.className = 'vech-tree-empty';
                tree.append(empty);
            });
            applySearch();
        }

        function createNode(node, side) {
            var wrapper = document.createElement('div');
            var line = document.createElement('div');
            var row = document.createElement('div');
            var branch = document.createElement(node.children.length ? 'button' : 'span');
            var copy = document.createElement('span');
            var label = document.createElement('strong');
            var metadata = document.createElement('span');
            var currentPathSegment;
            var actions = document.createElement('span');
            var children = document.createElement('div');
            var sourceItems = node.sourceItems || [];
            var sourceNode = node.entityType === 'source';
            var mappedSourceItems = !sourceNode && !node.item.is_source_only ? sourceItems : [];
            var sourceCodes = uniqueValues(mappedSourceItems.map(function (sourceItem) {
                return String(sourceItem.identifier || '').trim();
            })).filter(Boolean);
            var isUnmapped = node.change && (node.change.actions || []).indexOf('disconnected') !== -1;
            var isMappingCreated = !node.ghost && node.change &&
                (node.change.actions || []).some(function (action) {
                    return action === 'connected' || action === 'reconnected';
                });
            var sourceCodeText;
            var sourceCodeSuffix;
            var magentoPath = !sourceNode && !node.item.is_source_only
                ? getMagentoPathPresentation(node.item.path, node.identifier)
                : null;
            var nodeKey = side + ':' + node.key;
            var searchText = [node.item.label || '', node.identifier, node.item.path || ''];
            var description = magentoPath ? magentoPath.text : node.identifier;
            var labelText = node.item.label || node.identifier;

            if (!sourceNode && node.item.category_code &&
                sourceCodes.indexOf(String(node.item.category_code).trim()) === -1
            ) {
                sourceCodes.push(String(node.item.category_code).trim());
            }
            sourceCodeText = sourceCodes.join(', ');
            sourceCodeSuffix = sourceCodeText ? ' (' + sourceCodeText + ')' : '';

            wrapper.className = 'vec-tree-node vech-node';
            wrapper.dataset.key = nodeKey;
            line.className = 'vec-node-row vech-node-line';
            row.className = 'vec-node-card vech-node-row ' + (sourceNode ? 'vec-ergo-card' : 'vec-magento-card');
            row.dataset.nodeKey = node.identifier;
            row.dataset.connected = sourceNode && Number(node.item.magento_category_id) > 0 ? 'true' : 'false';
            row.dataset.changeKeys = JSON.stringify(node.changeKeys || []);
            sourceItems.forEach(function (sourceItem) {
                searchText.push(sourceItem.label || '', sourceItem.identifier || '');
            });
            row.dataset.search = searchText.join(' ').toLowerCase();
            if (node.change) {
                row.dataset.hasChange = 'true';
                if (side === 'target') {
                    row.classList.add('has-change');
                }
                (node.change.actions || []).forEach(function (action) {
                    row.classList.add('is-' + safeToken(action));
                });
                row.classList.add('is-primary-' + safeToken(
                    selectPrimaryAction(node.change.actions || [], node.ghost)
                ));
            }
            if (node.ghost) {
                row.classList.add('is-ghost', 'is-ghost-' + node.ghost);
            }
            if (node.item.is_source_only) {
                row.classList.add('is-source-only');
            }
            if (sourceItems.length) {
                row.classList.add('has-source');
            }
            if (node.children.length) {
                var chevron = document.createElement('span');

                branch.type = 'button';
                branch.className = 'vec-tree-toggle';
                branch.dataset.role = 'toggle-children';
                chevron.className = 'vec-tree-toggle-icon';
                chevron.setAttribute('aria-hidden', 'true');
                branch.append(chevron);
                branch.addEventListener('click', function () {
                    collapsedNodes[nodeKey] = branch.getAttribute('aria-expanded') === 'true';
                    setNodeExpanded(wrapper, !collapsedNodes[nodeKey]);
                });
            } else {
                branch.className = 'vec-tree-toggle-spacer';
                branch.setAttribute('aria-hidden', 'true');
            }
            copy.className = 'vec-card-copy';
            label.className = 'vech-node-label';
            label.dataset.categoryLabel = labelText;
            if (sourceCodeSuffix) {
                description += sourceCodeSuffix;
            }
            label.textContent = labelText;
            label.title = label.textContent;
            if (node.ghost === 'mapping-from') {
                description += ' · ' + $t('Previous mapping');
            } else if (node.ghost === 'from') {
                description += ' · ' + $t('Previous location');
            }
            if (sourceNode && node.item.magento_category_id) {
                description += ' · Magento #' + node.item.magento_category_id;
                searchText.push(node.item.magento_label || '', String(node.item.magento_category_id));
                row.dataset.search = searchText.join(' ').toLowerCase();
            } else if (!sourceNode && node.item.is_source_only && node.ghost !== 'mapping-from') {
                description += ' · ' + $t('Not mapped');
            }
            metadata.title = description;
            if (magentoPath) {
                var pathValue = document.createElement('span');

                metadata.className = 'vec-card-path';
                pathValue.className = 'vech-magento-path-value';
                pathValue.append(document.createTextNode(magentoPath.prefix));
                currentPathSegment = document.createElement('strong');

                currentPathSegment.className = 'vec-card-path-current';
                currentPathSegment.textContent = magentoPath.current;
                pathValue.append(currentPathSegment);
                metadata.append(pathValue);
                if (description !== magentoPath.text) {
                    if (sourceCodeSuffix) {
                        var ergonodeCode = document.createElement(isMappingCreated ? 'strong' : 'span');

                        ergonodeCode.className = 'vech-ergonode-code' +
                            (isUnmapped ? ' vech-unmapped-code' : '') +
                            (isMappingCreated ? ' vech-mapped-code' : '');
                        ergonodeCode.textContent = sourceCodeText;
                        metadata.append(
                            document.createTextNode(' ('),
                            ergonodeCode,
                            document.createTextNode(')')
                        );
                        if (description.length > magentoPath.text.length + sourceCodeSuffix.length) {
                            metadata.append(document.createTextNode(
                                description.slice(magentoPath.text.length + sourceCodeSuffix.length)
                            ));
                        }
                    } else {
                        metadata.append(document.createTextNode(description.slice(magentoPath.text.length)));
                    }
                }
            } else if (sourceNode || node.item.is_source_only) {
                var sourceCode = document.createElement('span');

                sourceCode.className = 'vech-ergonode-code';
                sourceCode.textContent = node.identifier;
                metadata.append(sourceCode);
                if (description.length > String(node.identifier).length) {
                    metadata.append(document.createTextNode(description.slice(String(node.identifier).length)));
                }
            } else {
                metadata.textContent = description;
            }
            copy.append(label);
            if (description) {
                copy.append(metadata);
            }
            actions.className = 'vech-node-actions';
            if (node.change && (side !== 'source' ||
                selectPrimaryAction(node.change.actions || [], node.ghost) !== 'disconnected')) {
                actions.append(createChangeIndicator(node.change, node.ghost, node.parentIdentifier));
            } else if (node.createdAncestor) {
                row.dataset.hasChange = 'true';
                row.classList.add('is-created-descendant');
                actions.append(createChangeIndicator({actions: ['created']}, null, null, node.createdAncestor));
            }
            row.append(copy);
            if (actions.children.length) {
                row.append(actions);
            }
            line.append(branch, row);
            wrapper.append(line);
            children.className = 'vec-node-children vech-node-children';
            node.children.forEach(function (child) {
                children.append(createNode(child, side));
            });
            if (node.children.length) {
                wrapper.append(children);
                if (!Object.prototype.hasOwnProperty.call(collapsedNodes, nodeKey)) {
                    collapsedNodes[nodeKey] = !node.isConfiguredRoot && !children.querySelector('[data-has-change="true"]');
                }
                setNodeExpanded(wrapper, !collapsedNodes[nodeKey]);
            }

            return wrapper;
        }

        function getMagentoPathPresentation(path, fallbackIdentifier) {
            var segments = String(path || '').split('/').filter(Boolean);
            var current;
            var prefix;

            if (!segments.length) {
                segments.push(String(fallbackIdentifier || ''));
            }
            current = segments.pop();
            prefix = '/' + (segments.length ? segments.join('/') + '/' : '');

            return {
                current: current,
                prefix: prefix,
                text: prefix + current
            };
        }

        function setNodeExpanded(wrapper, expanded) {
            var branch = wrapper.querySelector(':scope > .vech-node-line > [data-role="toggle-children"]');
            var children = wrapper.querySelector(':scope > .vech-node-children');

            if (!branch || !children) {
                return;
            }
            branch.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            branch.setAttribute('aria-label', (expanded ? $t('Collapse children') : $t('Expand children')) +
                ': ' + wrapper.querySelector('.vech-node-label').dataset.categoryLabel);
            children.hidden = !expanded;
        }

        function createChangeIndicator(change, ghost, previousParent, createdAncestor) {
            var trigger = document.createElement('span');
            var icon = document.createElement('span');
            var tooltip = document.createElement('span');
            var title = document.createElement('strong');
            var relatedChanges = change.related_changes || [change];
            var primaryAction = selectPrimaryAction(change.actions || [], ghost);
            var changeLabel = createdAncestor ? $t('Child of a created category')
                : (change.actions || []).map(actionLabel).join(' · ');

            trigger.className = 'vech-change-indicator vech-change-indicator-' + safeToken(primaryAction);
            trigger.tabIndex = 0;
            trigger.setAttribute('role', 'img');
            trigger.setAttribute('aria-label', changeLabel);
            icon.className = 'vech-change-icon ' + actionIconClass(primaryAction);
            icon.setAttribute('aria-hidden', 'true');
            tooltip.className = 'vech-tooltip';
            tooltip.id = 'vech-change-tooltip-' + (++changeHintSequence);
            tooltip.setAttribute('role', 'tooltip');
            trigger.setAttribute('aria-describedby', tooltip.id);
            title.textContent = changeLabel;
            tooltip.append(title);
            if (createdAncestor) {
                var context = document.createElement('span');

                context.textContent = $t('This category belongs to the branch created at %1.')
                    .replace('%1', createdAncestor.label || createdAncestor.identifier);
                tooltip.append(context);
            }
            (createdAncestor ? [] : relatedChanges).forEach(function (relatedChange) {
                describeChange(relatedChange, ghost, previousParent).forEach(function (line) {
                    var text = document.createElement('span');

                    text.textContent = relatedChanges.length > 1
                        ? entityTypeLabel(relatedChange.entity_type) + ': ' + line
                        : line;
                    tooltip.append(text);
                });
            });
            trigger.append(icon, tooltip);

            return trigger;
        }

        function selectPrimaryAction(actions, ghost) {
            var priorities = ['deleted', 'moved', 'source_moved', 'created', 'excluded', 'included',
                'reconnected', 'connected', 'disconnected', 'renamed'];
            var reorderedOnly = actions.length > 0 && actions.every(function (action) {
                return action === 'reordered' || action === 'source_reordered';
            });

            if (ghost === 'mapping-from') {
                return 'reconnected';
            }
            if (reorderedOnly) {
                return 'reordered';
            }
            if (ghost === 'from') {
                return 'moved';
            }

            return priorities.find(function (action) {
                return actions.indexOf(action) !== -1;
            }) || actions[0] || 'other';
        }

        function actionIconClass(action) {
            if (action === 'deleted') {
                return 'vec-delete-snapshot-icon';
            }
            if (action === 'created') {
                return 'vech-change-icon-created';
            }
            if (action === 'moved' || action === 'source_moved') {
                return 'vech-change-icon-moved';
            }
            if (action === 'reordered' || action === 'source_reordered') {
                return 'vech-change-icon-reordered';
            }
            if (action === 'included') {
                return 'vech-change-icon-included';
            }
            if (action === 'excluded') {
                return 'vech-change-icon-excluded';
            }
            if (action === 'disconnected') {
                return 'vech-change-icon-disconnected vec-unmap-icon';
            }
            if (action === 'connected' || action === 'reconnected') {
                return 'vech-change-icon-connected veui-connected-icon';
            }
            if (action === 'renamed') {
                return 'veui-section-navigation-icon veui-section-navigation-icon-attribution-pen';
            }

            return 'vech-change-icon-other';
        }

        function describeChange(change, ghost, previousParent) {
            var before = change.before || {};
            var after = change.after || {};
            var lines = [];

            if (ghost === 'from') {
                lines.push($t('Previous parent: %1').replace('%1', displayValue(previousParent)));
            }
            if ((change.actions || []).indexOf('moved') !== -1) {
                lines.push($t('Parent: %1 → %2')
                    .replace('%1', displayValue(before.parent_identifier))
                    .replace('%2', displayValue(after.parent_identifier)));
            }
            if ((change.actions || []).indexOf('reordered') !== -1) {
                lines.push($t('Position: %1 → %2')
                    .replace('%1', displayValue(before.sort_order))
                    .replace('%2', displayValue(after.sort_order)));
            }
            if ((change.actions || []).indexOf('source_moved') !== -1) {
                lines.push($t('Ergonode parent: %1 → %2')
                    .replace('%1', displayValue(before.source_parent_identifier))
                    .replace('%2', displayValue(after.source_parent_identifier)));
            }
            if ((change.actions || []).indexOf('source_reordered') !== -1) {
                lines.push($t('Ergonode position: %1 → %2')
                    .replace('%1', displayValue(before.source_sort_order))
                    .replace('%2', displayValue(after.source_sort_order)));
            }
            if ((change.actions || []).indexOf('renamed') !== -1) {
                lines.push($t('Name: %1 → %2')
                    .replace('%1', displayValue(before.label))
                    .replace('%2', displayValue(after.label)));
            }
            if (hasMappingAction(change.actions || [])) {
                lines.push($t('Magento: %1 → %2')
                    .replace('%1', mappingValue(before))
                    .replace('%2', mappingValue(after)));
            }
            if ((change.actions || []).indexOf('included') !== -1 ||
                (change.actions || []).indexOf('excluded') !== -1) {
                lines.push($t('Visibility: %1 → %2')
                    .replace('%1', before.active ? $t('included') : $t('excluded'))
                    .replace('%2', after.active ? $t('included') : $t('excluded')));
            }
            if ((change.actions || []).indexOf('created') !== -1) {
                lines.push($t('The category appears at this location after the operation.'));
            }
            if ((change.actions || []).indexOf('deleted') !== -1) {
                lines.push($t('This is the last location before deletion.'));
            }

            return lines;
        }

        function focusChange(change) {
            var changeKey = change.entity_type + ':' + change.entity_identifier;
            var treeElement = root.querySelector('[data-role="tree"][data-side="' + change.entity_type + '"]');
            var search = root.querySelector('[data-role="tree-search"][data-side="' + change.entity_type + '"]');
            window.setTimeout(function () {
                var candidates = treeElement.querySelectorAll('[data-node-key]');
                var target = Array.prototype.find.call(candidates, function (candidate) {
                    return hasChangeKey(candidate, changeKey) && !candidate.classList.contains('is-ghost');
                }) || Array.prototype.find.call(candidates, function (candidate) {
                    return hasChangeKey(candidate, changeKey);
                });

                treeElement.querySelectorAll('.is-focused').forEach(function (row) {
                    row.classList.remove('is-focused');
                });
                if (target) {
                    var ancestor = target.closest('.vech-node').parentElement.closest('.vech-node');

                    if (change.entity_type === 'source' && target.dataset.connected === 'true') {
                        hideConnected = false;
                        syncConnectedToggle();
                    }
                    if (search && search.value) {
                        search.value = '';
                    }
                    applySearch();
                    while (ancestor) {
                        collapsedNodes[ancestor.dataset.key] = false;
                        setNodeExpanded(ancestor, true);
                        ancestor = ancestor.parentElement.closest('.vech-node');
                    }
                    target.classList.add('is-focused');
                    target.tabIndex = -1;
                    target.focus({preventScroll: true});
                    target.scrollIntoView({behavior: 'smooth', block: 'center'});
                }
            }, 0);
        }

        function hasChangeKey(row, changeKey) {
            var keys;

            try {
                keys = JSON.parse(row.dataset.changeKeys || '[]');
            } catch (error) {
                keys = [];
            }

            return keys.indexOf(changeKey) !== -1;
        }

        function applySearch() {
            root.querySelectorAll('[data-role="tree"]').forEach(function (tree) {
                var search = root.querySelector('[data-role="tree-search"][data-side="' + tree.dataset.side + '"]');
                var query = search ? search.value.trim().toLowerCase() : '';
                var empty = tree.querySelector('.vech-tree-empty');
                var nodes = tree.querySelectorAll('.vech-node');

                Array.from(nodes).reverse().forEach(function (wrapper) {
                    var row = wrapper.querySelector(':scope > .vech-node-line > .vech-node-row');
                    var children = wrapper.querySelector(':scope > .vech-node-children');
                    var hasVisibleChildren = children && Array.from(children.children).some(function (child) {
                        return !child.hidden;
                    });
                    var hiddenConnection = tree.dataset.side === 'source' && hideConnected &&
                        row.dataset.connected === 'true';

                    wrapper.hidden = (hiddenConnection || (query && row.dataset.search.indexOf(query) === -1)) &&
                        !hasVisibleChildren;
                    wrapper.classList.toggle('vech-leaf', !hasVisibleChildren);
                    row.classList.toggle('is-mapped-parent', hiddenConnection && Boolean(hasVisibleChildren));
                    setNodeExpanded(wrapper, query ? true : !collapsedNodes[wrapper.dataset.key]);
                });
                if (empty) {
                    empty.hidden = Array.from(nodes).some(function (node) { return !node.hidden; });
                    empty.textContent = query ? $t('No categories match your search.') : changesOnly && !nodes.length ?
                        $t('No changes to show.') :
                        (nodes.length && tree.dataset.side === 'source' && hideConnected
                            ? $t('All categories are connected.')
                            : (tree.dataset.side === 'source'
                                ? $t('No categories in the Ergonode tree.')
                                : $t('No categories in the Magento tree.')));
                }
            });
        }

        function showMessage(message) {
            var messageElement = root.querySelector('[data-role="message"]');

            if (messageElement) {
                messageElement.textContent = message;
                messageElement.hidden = false;
            }
        }

        function hideMessage() {
            var messageElement = root.querySelector('[data-role="message"]');

            if (messageElement) {
                messageElement.hidden = true;
            }
        }

        function actionLabel(action) {
            return $t(actionLabels[action] || action);
        }

        function actionGroup(action) {
            if (action === 'created') {
                return 'created';
            }
            if (action === 'deleted') {
                return 'deleted';
            }
            if (action === 'moved' || action === 'source_moved') {
                return 'moved';
            }
            if (action === 'disconnected') {
                return 'unmapped';
            }
            if (hasMappingAction([action])) {
                return 'mapping';
            }

            return 'other';
        }

        function hasMappingAction(actions) {
            return ['connected', 'disconnected', 'reconnected'].some(function (action) {
                return actions.indexOf(action) !== -1;
            });
        }

        function mappingValue(snapshot) {
            if (!snapshot || !snapshot.magento_category_id) {
                return $t('none');
            }

            return snapshot.magento_label || '#' + snapshot.magento_category_id;
        }

        function displayValue(value) {
            return value === null || typeof value === 'undefined' || value === '' ? '—' : String(value);
        }

        function uniqueValues(values) {
            return values.filter(function (value, index) {
                return values.indexOf(value) === index;
            });
        }

        function findSelectedOperation() {
            if (!state || !state.operation) {
                return null;
            }

            return operations.find(function (operation) {
                return Number(operation.operation_id) === Number(state.operation.operation_id);
            }) || null;
        }

        function pickDefaultChange(changes) {
            if (!changes.length) {
                return null;
            }

            return changes.reduce(function (selected, change) {
                return (change.actions || []).length > (selected.actions || []).length ? change : selected;
            }, changes[0]);
        }

        function isSameChange(left, right) {
            if (!left || !right) {
                return false;
            }
            if (left.change_id && right.change_id) {
                return Number(left.change_id) === Number(right.change_id);
            }

            return left.entity_type === right.entity_type && left.entity_identifier === right.entity_identifier;
        }

        function uniqueCategoryCount(changes) {
            var identifiers = {};

            changes.forEach(function (change) {
                identifiers[change.category_code || change.entity_identifier] = true;
            });

            return Object.keys(identifiers).length;
        }

        function countChanges(changes, actions) {
            return changes.filter(function (change) {
                return (change.actions || []).some(function (action) {
                    return actions.indexOf(action) !== -1;
                });
            }).length;
        }

        function entityTypeLabel(entityType) {
            return entityType === 'source' ? $t('Ergonode') : $t('Magento');
        }

    };
});
