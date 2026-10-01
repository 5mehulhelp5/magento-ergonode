define([
    'jquery',
    'mage/translate',
    'Ergonode_CoreAdminUi/js/entity-options',
    'Ergonode_CategoryPublisherAdminUi/js/category-code-generator',
    'Ergonode_CategoryPublisherAdminUi/js/category-publish-progress',
    'Ergonode_PublisherAdminUi/js/json-post',
    'Ergonode_PublisherAdminUi/js/manual-auth',
    'Ergonode_CategoryPublisherAdminUi/js/publication-readiness'
], function ($, $t, entityOptions, categoryCodeGenerator, createCategoryPublishProgress, jsonPost, createManualAuth, createPublicationReadiness) {
    'use strict';

    var treeDialogSequence = 0;
    var initialized = new WeakSet();

    return function (config, element) {
        if (initialized.has(element)) {
            return;
        }
        initialized.add(element);

        var $root = $(element);
        var api = element.veaCategoryMappingApi || null;
        var observer = null;
        var destroyed = false;
        var cleanupRegistered = false;
        var cancelPause = null;
        var cancelRetry = null;
        var publishInProgress = false;
        var progress = createCategoryPublishProgress();
        var pendingTooltipSequence = 0;
        var auth = createManualAuth(config);
        var preparingCategories = false;
        var readiness = createPublicationReadiness(config, function () {
            if (destroyed) { return; }
            updateBulkControls();
            element.querySelectorAll('[data-role="create-ergonode-category"]').forEach(function (button) {
                button.disabled = !readiness.isReady() || publishInProgress || button.classList.contains('is-working');
            });
        });
        var treeDialog = createTreeDialog(config, $root);
        var createCategoryTitle = $t(
            'Utwórz kategorię w Ergonode i zapisz mapowanie z kategorią Magento.'
        );

        $root.on('ergonode:category-mapping:ready.ergonodeCategoryPublisher', function (event, mappingApi) {
            api = mappingApi;
            injectActions();
        });
        $root.on('click.ergonodeCategoryPublisher', '[data-role="create-ergonode-category"]', function (event) {
            var $button = $(this);

            event.preventDefault();
            if ($button.prop('disabled') || publishInProgress) {
                return;
            }
            setIndividualButtonBusy($button, true);
            ensurePublicationReady().then(function (authenticated) {
                if (authenticated) {
                    var magentoId = Number($button.data('magento-id') || 0);

                    createCategoryImmediately($button, magentoId);
                    return;
                }
                setIndividualButtonBusy($button, false);
            });
        });
        $root.on('ergonode:category-mapping:selection-changed.ergonodeCategoryPublisher', function () {
            updateBulkControls();
        });
        $root.on('click.ergonodeCategoryPublisher', '[data-role="create-selected-ergonode-categories"]', function (event) {
            var $button = $(event.currentTarget);

            event.preventDefault();
            if ($button.prop('disabled') || preparingCategories || publishInProgress) {
                return;
            }
            preparingCategories = true;
            $button.prop('disabled', true).addClass('is-working');
            ensurePublicationReady().then(function (authenticated) {
                if (authenticated) {
                    createSelectedCategories();
                }
                preparingCategories = false;
                $button.removeClass('is-working');
                updateBulkControls();
            });
        });
        function handleCreateTreeClick(event) {
            var button = event.currentTarget;

            event.preventDefault();
            auth.ensure().then(function (authenticated) {
                if (authenticated) {
                    treeDialog.open(button);
                }
            });
        }

        element.addEventListener('click', interceptSave, true);

        function interceptSave(event) {
            var unmapButton = event.target.closest('[data-role="unmap-category"]');
            var saveButton = event.target.closest('[data-role="save-categories"]');

            if (unmapButton && cancelPendingCategory(String(unmapButton.getAttribute('data-code') || ''))) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            if (!saveButton || publishInProgress || !requiresManualApi()) {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            publishInProgress = true;
            setProcessBusy(saveButton, true);
            ensurePublicationReady().then(function (authenticated) {
                if (!authenticated) {
                    publishInProgress = false;
                    setProcessBusy(saveButton, false);
                    return;
                }
                runPublishProcess(saveButton);
            });
        }

        if (window.MutationObserver) {
            observer = new window.MutationObserver(injectActions);
            observer.observe(element, {childList: true, subtree: true});
            element.veaErgonodeCategoryPublisherObserver = observer;
        }
        injectActions();
        return {destroy: destroy};

        function recoverSave(response) {
            return response.failure_type === 'authentication_required' ? auth.ensure() : false;
        }

        function ensurePublicationReady() {
            return readiness.ensure().then(function (ready) {
                return ready && !destroyed ? auth.ensure() : false;
            });
        }

        function injectActions() {
            if (destroyed) { return; }
            if (!api) {
                api = element.veaCategoryMappingApi || null;
            }
            if (api) {
                if (!cleanupRegistered && typeof api.cleanup === 'function') {
                    api.cleanup(destroy);
                    cleanupRegistered = true;
                }
                if (typeof api.setSaveRecoveryHandler === 'function') {
                    api.setSaveRecoveryHandler(recoverSave);
                }
                decoratePendingCategories();
                injectBulkActions();
                $root.find('.vec-magento-card').each(function () {
                    var $card = $(this);
                    var $mapping = $card.find('.vec-magento-mapping.is-empty').first();
                    var $optionsMenu = $card.find('.veui-entity-options-menu').first();
                    var magentoId = Number($card.data('magento-id') || 0);
                    var label = $card.find('.vec-card-copy strong').first().text();

                    if (!magentoId || !$mapping.length || !$optionsMenu.length || $card.hasClass('is-blocked')) {
                        return;
                    }
                    if (!$optionsMenu.find('[data-role="create-ergonode-category"]').length) {
                        $optionsMenu.prepend(entityOptions.createAction({
                            role: 'create-ergonode-category',
                            className: 'vec-create-ergonode-category',
                            iconClass: 'vec-create-ergonode-category-icon',
                            label: $t('Utwórz w Ergonode'),
                            disabled: !readiness.isReady(),
                            title: createCategoryTitle,
                            ariaLabel: createCategoryTitle,
                            attributes: {'data-magento-id': magentoId}
                        }));
                    }
                });
                updateBulkControls();
            }
            $root.find('[data-role="refresh-tree-options"]').each(function () {
                var $button;

                if ($root.find('[data-role="create-ergonode-tree"]').length) {
                    return;
                }
                $button = $('<button/>', {
                    type: 'button',
                    class: 'veui-button veui-button-toolbar vec-create-ergonode-tree',
                    title: $t('Utwórz drzewo w Ergonode'),
                    'aria-label': $t('Utwórz drzewo w Ergonode'),
                    'aria-haspopup': 'dialog',
                    'aria-expanded': 'false',
                    'aria-controls': treeDialog.id,
                    'data-role': 'create-ergonode-tree'
                }).append(
                    $('<span/>', {class: 'veui-create-ergonode-icon', 'aria-hidden': 'true'}),
                    $('<span/>').text($t('Create'))
                );
                $button.get(0).addEventListener('click', handleCreateTreeClick);
                $(this).after($button);
            });
        }

        function decoratePendingCategories() {
            var pendingByCode = Object.create(null);
            var pendingByMagentoId = Object.create(null);

            api.getCategories().forEach(function (category) {
                var extension = category.extension_data && category.extension_data.to_ergonode;
                var magentoId = Number(category.magento_category_id || 0);

                if (!extension || !extension.pending_create) {
                    return;
                }
                pendingByCode[String(category.code || '')] = true;
                if (magentoId > 0) {
                    pendingByMagentoId[String(magentoId)] = true;
                }
            });
            $root.find('.vec-ergo-card[data-code]').each(function () {
                decoratePendingElement(
                    $(this),
                    !!pendingByCode[this.getAttribute('data-code')],
                    'pending-ergonode-category-card'
                );
            });
            $root.find('.vec-magento-card[data-magento-id]').each(function () {
                var $card = $(this);
                var isPending = !!pendingByMagentoId[String(Number($card.data('magento-id') || 0))];

                decoratePendingElement(
                    $card,
                    isPending,
                    'pending-ergonode-category-mapping'
                );
            });
        }

        function decoratePendingElement($element, isPending, role) {
            var $icon;
            var $mapping;
            var $options;
            var categoryLabel;
            var pendingMessage;
            var tooltipId;

            if (!$element.length) {
                return;
            }
            $element.toggleClass('is-pending-create', isPending);
            $icon = $element.find('[data-role="' + role + '"]').first();
            if (!isPending) {
                $icon.remove();
                return;
            }
            if ($icon.length) {
                return;
            }
            $mapping = $element.find('.vec-magento-mapping.is-mapped').first();
            categoryLabel = String($mapping.data('mapping-label') || '');
            if (!categoryLabel) {
                categoryLabel = $element.find('.vec-card-copy strong').first().text();
            }
            pendingMessage = $t(
                'Kategoria „%1” nie istnieje jeszcze w Ergonode. Kliknij „Zapisz”, aby ją utworzyć i zapisać mapowanie.'
            ).replace('%1', categoryLabel);
            tooltipId = 'vec-pending-ergonode-tooltip-' + (++pendingTooltipSequence);
            $icon = $('<button/>', {
                type: 'button',
                class: 'vec-pending-ergonode-category',
                'data-role': role,
                'aria-label': pendingMessage,
                'aria-describedby': tooltipId
            }).attr('draggable', 'false').append(
                $('<span/>', {
                    class: 'vec-pending-ergonode-category-icon',
                    'aria-hidden': 'true'
                }),
                $('<span/>', {
                    class: 'vec-pending-ergonode-category-tooltip',
                    id: tooltipId,
                    role: 'tooltip'
                }).text(pendingMessage)
            );
            $options = $element.find('[data-role="entity-options"]').first();
            if ($options.length) {
                $options.before($icon);
            } else {
                $element.append($icon);
            }
        }

        function injectBulkActions() {
            var $menu = $root.find(
                '[data-bulk-options-source="magento"] .veui-entity-options-menu'
            ).first();
            var action;
            var count;

            if (!$menu.length || $menu.find('[data-role="create-selected-ergonode-categories"]').length) {
                return;
            }

            action = entityOptions.createAction({
                role: 'create-selected-ergonode-categories',
                className: 'vec-create-selected-ergonode',
                iconClass: 'veui-create-ergonode-icon',
                label: $t('Utwórz w Ergonode'),
                disabled: true
            });
            count = $('<span/>', {
                class: 'vec-selected-count',
                'data-role': 'selected-ergonode-count'
            }).text('0').get(0);
            action.appendChild(count);
            $menu.prepend(action);
        }

        function selectedMagentoIdsForCreation(models) {
            var selected;
            var roots = Object.create(null);

            if (!api || typeof api.getBulkSelection !== 'function') {
                return [];
            }
            selected = api.getBulkSelection('magento');
            if (!selected.length) {
                return [];
            }
            models = models || createDraftModels();
            $root.find('.vec-magento-card.is-configured-root').each(function () {
                roots[Number($(this).data('magento-id') || 0)] = true;
            });

            return selected.map(function (id) {
                return String(Number(id));
            }).filter(function (id) {
                var category = models.magentoById[id];

                return Number(id) > 0 && category && category.active !== false
                    && !models.mappedByMagentoId[id] && !roots[id];
            });
        }

        function updateBulkControls() {
            var count = selectedMagentoIdsForCreation().length;
            var $count = $root.find('[data-role="selected-ergonode-count"]').first();

            $root.find('[data-role="create-selected-ergonode-categories"]')
                .prop('disabled', count === 0 || !readiness.isReady() || preparingCategories || publishInProgress);
            if ($count.length && $count.text() !== String(count)) {
                $count.text(String(count));
            }
        }

        function createSelectedCategories() {
            var models = createDraftModels();
            var selectedMagentoIds = selectedMagentoIdsForCreation(models);

            selectedMagentoIds.sort(function (left, right) {
                var first = models.magentoById[left] || {};
                var second = models.magentoById[right] || {};

                return Number(first.level || 0) - Number(second.level || 0)
                    || Number(first.position || 0) - Number(second.position || 0)
                    || Number(left) - Number(right);
            });
            api.batchUpdate(function () {
                selectedMagentoIds.forEach(function (id) {
                    addPendingCategory(Number(id), models);
                });
            });
            if (typeof api.clearBulkSelection === 'function') {
                api.clearBulkSelection('magento');
            }
        }

        function cancelPendingCategory(code) {
            var category;

            if (!api || typeof api.removeCategory !== 'function') {
                return false;
            }
            category = api.getCategories().filter(function (item) {
                var extension = item.extension_data && item.extension_data.to_ergonode;

                return item.code === code && !!(extension && extension.pending_create);
            })[0];
            if (!category || !api.removeCategory(code)) {
                return false;
            }

            updateBulkControls();

            return true;
        }

        function requiresManualApi() {
            return !!api && api.getCategories().some(requiresCategoryApi);
        }

        function requiresCategoryApi(category) {
            var extension = category.extension_data && category.extension_data.to_ergonode;

            // Imported snapshot categories may have no cached REST identity.
            // Only an explicit creation draft belongs in the publication queue.
            return !!(extension && extension.pending_create);
        }

        function runPublishProcess(saveButton) {
            var categories = api.getCategories();
            var queue = sortPublishItems(categories.filter(requiresCategoryApi), categories);
            var pauseRequested = false;
            var resumeQueue = null;

            progress.open(queue.length, {
                pause: function () {
                    pauseRequested = true;
                },
                resume: function () {
                    pauseRequested = false;
                    if (resumeQueue) {
                        var resolve = resumeQueue;

                        resumeQueue = null;
                        cancelPause = null;
                        resolve();
                    }
                }
            });
            return validateLayoutBeforePublication()
                .then(function () {
                    return processCategoryQueue(queue, categories, waitUntilResumed);
                })
                .then(function (outcome) {
                    assertMounted();
                    progress.finalizing();

                    return saveLayoutWithRetry(
                        outcome.excludedCodes,
                        function (seconds, message) {
                            return progress.wait(
                                seconds,
                                message || $t('Ergonode ograniczyło liczbę zapytań.')
                            );
                        },
                        $t('Nie udało się zapisać drzewa kategorii.'),
                        function () {
                            outcome.preparedCodes.forEach(function (code) {
                                if (outcome.excludedCodes.indexOf(code) === -1) {
                                    api.markCategoryPublished(code);
                                }
                            });
                        }
                    ).then(function () {
                        if (outcome.excludedCodes.length) {
                            api.markDirty();
                            progress.complete(true);
                            return;
                        }
                        progress.complete(false);
                    });
                })
                .catch(function (error) {
                    progress.fail(error && error.message
                        ? error.message
                        : $t('Nie udało się zakończyć procesu tworzenia kategorii.'));
                })
                .then(function () {
                    publishInProgress = false;
                    setProcessBusy(saveButton, false);
                });

            function waitUntilResumed() {
                assertMounted();
                if (!pauseRequested) {
                    return Promise.resolve();
                }
                progress.setPauseState('paused');

                return new Promise(function (resolve, reject) {
                    resumeQueue = resolve;
                    cancelPause = function () { reject(new Error('Publication view has been disposed.')); };
                });
            }
        }

        function processCategoryQueue(queue, categories, waitUntilResumed) {
            var batchSize = Math.max(1, Math.min(50, Number(config.category_batch_size || 50)));
            var batchCount = Math.ceil(queue.length / batchSize);
            var categoryByCode = Object.create(null);
            var failedCodes = Object.create(null);
            var blockedCodes = Object.create(null);
            var preparedCodes = Object.create(null);
            var retry = boundedRetry(progress.wait.bind(progress));

            categories.forEach(function (category) {
                categoryByCode[category.code] = category;
            });

            return processAt(0).then(function () {
                return {
                    excludedCodes: Object.keys(failedCodes).concat(Object.keys(blockedCodes)),
                    preparedCodes: Object.keys(preparedCodes)
                };
            });

            function processAt(offset) {
                return waitUntilResumed().then(function () {
                    return processBatch(offset);
                });
            }

            function processBatch(offset) {
                var batch;
                var sendItems = [];
                var immediateResults = [];

                if (offset >= queue.length) {
                    return Promise.resolve();
                }
                batch = queue.slice(offset, offset + batchSize);
                progress.showBatch(Math.floor(offset / batchSize) + 1, batchCount, batch);
                batch.forEach(function (item) {
                    if (hasBlockedAncestor(item.code, categoryByCode, failedCodes, blockedCodes)) {
                        blockedCodes[item.code] = true;
                        immediateResults.push(blockedResult(item));
                    } else {
                        sendItems.push(item);
                    }
                });
                if (!sendItems.length) {
                    progress.applyBatch(immediateResults);
                    return processAt(offset + batch.length);
                }

                return sendCategoryBatch(sendItems).then(function (response) {
                    var resultByCode = Object.create(null);
                    var normalizedResults;

                    if (!response || !response.success) {
                        if (response && response.failure_type === 'retryable' && Number(response.retry_after_seconds || 0) > 0) {
                            return retry(
                                Number(response.retry_after_seconds),
                                response.message || $t('Ergonode ograniczyło liczbę zapytań.')
                            ).then(function () {
                                return processAt(offset);
                            });
                        }
                        throw new Error(response && response.message
                            ? response.message
                            : $t('Nie udało się przetworzyć paczki kategorii.'));
                    }
                    (response.items || []).forEach(function (item) {
                        resultByCode[String(item.code || '')] = item;
                    });
                    assertMounted();
                    normalizedResults = sendItems.map(function (item) {
                        return resultByCode[item.code] || {
                            code: item.code,
                            label: item.label,
                            status: 'failed',
                            message: $t('Backend nie zwrócił wyniku dla kategorii.')
                        };
                    });
                    normalizedResults.forEach(function (item) {
                        if (item.status === 'failed') {
                            failedCodes[item.code] = true;
                        } else if (item.status === 'skipped') {
                            blockedCodes[item.code] = true;
                            cancelPendingCategory(item.code);
                        }
                    });
                    normalizedResults = normalizedResults.map(function (item) {
                        if (item.remote_id) {
                            preparedCodes[item.code] = true;
                            api.markCategoryRemotePrepared(item.code, item.remote_id);
                        }
                        if (item.status !== 'failed' && item.status !== 'skipped'
                            && hasBlockedAncestor(item.code, categoryByCode, failedCodes, blockedCodes)
                        ) {
                            blockedCodes[item.code] = true;
                            return blockedResult(item);
                        }

                        return item;
                    });
                    retry = boundedRetry(progress.wait.bind(progress));
                    progress.applyBatch(immediateResults.concat(normalizedResults));

                    return processAt(offset + batch.length);
                });
            }
        }

        function sendCategoryBatch(items) {
            assertMounted();
            return jsonPost.post(
                config.urls.create_category_batch,
                config,
                {
                    category_tree_id: api.getCategoryTreeId(),
                    items: JSON.stringify(items)
                },
                $t('Nie udało się połączyć z Magento podczas przetwarzania paczki.')
            );
        }

        function saveLayoutWithRetry(excludedCodes, wait, fallbackMessage, beforeRender) {
            var retry = boundedRetry(wait);

            return new Promise(function (resolve, reject) {
                attempt();

                function attempt() {
                    if (destroyed) { reject(new Error('Publication view has been disposed.')); return; }
                    api.saveLayout(excludedCodes, beforeRender).done(function (response) {
                        if (response && response.success) {
                            resolve(response);
                            return;
                        }
                        if (response && response.failure_type === 'retryable' && Number(response.retry_after_seconds || 0) > 0) {
                            retry(
                                Number(response.retry_after_seconds),
                                response.message || ''
                            ).then(attempt, reject);
                            return;
                        }
                        reject(new Error(response && response.message
                            ? response.message
                            : fallbackMessage));
                    }).fail(function () {
                        reject(new Error(fallbackMessage));
                    });
                }
            });
        }

        function hasBlockedAncestor(code, categoryByCode, failedCodes, blockedCodes) {
            var category = categoryByCode[code];
            var parentCode = category ? String(category.parent_code || '') : '';
            var visited = Object.create(null);

            while (parentCode && !visited[parentCode]) {
                if (failedCodes[parentCode] || blockedCodes[parentCode]) {
                    return true;
                }
                visited[parentCode] = true;
                category = categoryByCode[parentCode];
                parentCode = category ? String(category.parent_code || '') : '';
            }

            return false;
        }

        function blockedResult(item) {
            return {
                code: String(item.code || ''),
                label: String(item.label || item.code || ''),
                status: 'blocked',
                message: $t('Kategoria nie została dodana do drzewa, ponieważ jej kategoria nadrzędna ma błąd.'),
                remote_id: item.remote_id || null
            };
        }

        function sortPublishItems(items, categories) {
            var categoryByCode = Object.create(null);

            categories.forEach(function (category) {
                categoryByCode[category.code] = category;
            });

            return items.slice().sort(function (left, right) {
                return categoryDepth(left, categoryByCode) - categoryDepth(right, categoryByCode)
                    || Number(left.sort_order || 0) - Number(right.sort_order || 0)
                    || String(left.code || '').localeCompare(String(right.code || ''));
            });
        }

        function categoryDepth(category, categoryByCode) {
            var depth = 0;
            var parentCode = String(category.parent_code || '');
            var visited = Object.create(null);

            while (parentCode && !visited[parentCode]) {
                visited[parentCode] = true;
                depth++;
                category = categoryByCode[parentCode] || {};
                parentCode = String(category.parent_code || '');
            }

            return depth;
        }

        function setProcessBusy(button, busy) {
            button.disabled = !!busy || !requiresManualApi();
            button.classList.toggle('is-working', !!busy);
            button.setAttribute('aria-busy', busy ? 'true' : 'false');
        }

        function setIndividualButtonBusy($button, busy) {
            $button.prop('disabled', !!busy || !readiness.isReady())
                .toggleClass('is-working', !!busy)
                .attr('aria-busy', busy ? 'true' : 'false');
        }

        function createCategoryImmediately($button, magentoId) {
            var saveButton = element.querySelector('[data-role="save-categories"]');
            var category;
            var remotePrepared = false;
            var reusedExisting = false;

            publishInProgress = true;
            category = addPendingCategory(magentoId);
            if (!category) {
                publishInProgress = false;
                setIndividualButtonBusy($button, false);
                return;
            }
            if (saveButton) {
                setProcessBusy(saveButton, true);
            }
            updateBulkControls();

            validateLayoutBeforePublication()
                .then(function () {
                    return sendSingleCategoryWithRetry(category);
                })
                .then(function (item) {
                    reusedExisting = item.status === 'existing';
                    if (!api.markCategoryRemotePrepared(category.code, item.remote_id)) {
                        throw new Error($t('Nie udało się przygotować mapowania utworzonej kategorii.'));
                    }
                    remotePrepared = true;

                    return saveLayoutWithRetry(
                        [],
                        waitForRetry,
                        $t(
                            'Kategoria powstała w Ergonode, ale nie udało się zapisać mapowania. Użyj „Zapisz”, aby ponowić.'
                        ),
                        function () {
                            api.markCategoryPublished(category.code);
                        }
                    );
                })
                .then(function () {
                    notify('success', reusedExisting
                        ? $t('Istniejąca kategoria Ergonode została przypięta do drzewa i zmapowana z Magento.')
                        : $t('Kategoria została utworzona w Ergonode i zmapowana z Magento.'));
                })
                .catch(function (error) {
                    if (!destroyed && !remotePrepared && !(error && error.retainDraft)
                        && api && typeof api.removeCategory === 'function'
                    ) {
                        api.removeCategory(category.code);
                    }
                    notify('error', error && error.message
                        ? error.message
                        : $t('Nie udało się utworzyć i zmapować kategorii w Ergonode.'));
                })
                .then(function () {
                    publishInProgress = false;
                    setIndividualButtonBusy($button, false);
                    if (saveButton) {
                        setProcessBusy(saveButton, false);
                    }
                });
        }

        function sendSingleCategoryWithRetry(category) {
            var retry = boundedRetry(waitForRetry);

            return attempt();

            function attempt() {
                return sendCategoryBatch([category]).then(function (response) {
                    var item;
                    var retryAfter = response ? Number(response.retry_after_seconds || 0) : 0;

                    if (response && !response.success && response.failure_type === 'retryable' && retryAfter > 0) {
                        return retry(retryAfter, response.message).then(attempt);
                    }
                    if (!response || !response.success) {
                        throw new Error(response && response.message
                            ? response.message
                            : $t('Nie udało się utworzyć kategorii w Ergonode.'));
                    }
                    assertMounted();
                    item = (response.items || []).filter(function (result) {
                        return String(result.code || '') === category.code;
                    })[0];
                    if (!item || item.status === 'failed' || !item.remote_id) {
                        throw new Error(item && item.message
                            ? item.message
                            : $t('Ergonode nie zwróciło identyfikatora utworzonej kategorii.'));
                    }

                    return item;
                });
            }
        }

        function validateLayoutBeforePublication() {
            return Promise.resolve().then(function () {
                assertMounted();
                return api.validateLayout();
            }).then(function (response) {
                if (!response || response.success !== true) {
                    throw new Error(response && response.message
                        ? response.message
                        : $t('Nie udało się sprawdzić mapowania kategorii.'));
                }
            });
        }

        function boundedRetry(wait) {
            var attempts = 1;
            var startedAt = Date.now();
            var delayedSeconds = 0;

            return function (seconds, message) {
                seconds = Number(seconds);
                if (!Number.isFinite(seconds) || seconds <= 0 || attempts >= 5
                    || Math.max((Date.now() - startedAt) / 1000, delayedSeconds) + seconds > 120
                ) {
                    var error = new Error((message ? message + ' ' : '') + $t(
                        'Automatyczne ponawianie zostało zatrzymane. Zachowano postęp; użyj „Zapisz”, aby wznowić.'
                    ));

                    error.retainDraft = true;
                    return Promise.reject(error);
                }
                attempts++;
                delayedSeconds += seconds;
                return wait(seconds, message);
            };
        }

        function waitForRetry(seconds) {
            assertMounted();
            return new Promise(function (resolve, reject) {
                var timer = window.setTimeout(function () {
                    cancelRetry = null;
                    resolve();
                }, Math.max(1, Number(seconds || 1)) * 1000);

                cancelRetry = function () {
                    window.clearTimeout(timer);
                    reject(new Error('Publication view has been disposed.'));
                };
            });
        }

        function assertMounted() {
            if (destroyed) {
                throw new Error('Publication view has been disposed.');
            }
        }

        function destroy() {
            if (destroyed) { return; }
            destroyed = true;
            if (cancelPause) { cancelPause(); cancelPause = null; }
            if (cancelRetry) { cancelRetry(); cancelRetry = null; }
            if (observer) { observer.disconnect(); }
            delete element.veaErgonodeCategoryPublisherObserver;
            $root.off('.ergonodeCategoryPublisher');
            element.removeEventListener('click', interceptSave, true);
            document.querySelectorAll('[data-role="create-ergonode-tree"][aria-controls="' + treeDialog.id + '"]')
                .forEach(function (button) {
                    button.removeEventListener('click', handleCreateTreeClick);
                    $(button).remove();
                });
            if (api && typeof api.setSaveRecoveryHandler === 'function') { api.setSaveRecoveryHandler(null); }
            treeDialog.destroy();
            auth.destroy();
            progress.destroy();
            initialized.delete(element);
        }

        function notify(type, message) {
            if (destroyed) { return; }
            if (api && typeof api.notify === 'function') {
                api.notify(type, message);
            }
        }

        function addPendingCategory(magentoId, models) {
            models = models || createDraftModels();
            var magento = models.magentoById[Number(magentoId || 0)];
            var code;
            var existing;
            var parentCode;
            var category;

            if (!magento) {
                notify('error', $t('Nie udało się przygotować kategorii do utworzenia w Ergonode.'));
                return false;
            }
            code = categoryPathCode(magento, models);
            existing = models.categoriesByCode[code] || null;
            if (existing) {
                reportCategoryCollision(magento, existing, code);
                notify(
                    'warning',
                    $t('Kategoria "%1" została pominięta: kod Ergonode "%2" jest już używany.')
                        .replace('%1', String(magento.label || magento.id))
                        .replace('%2', code)
                );
                return false;
            }
            parentCode = nearestMappedParentCode(magento, models);
            category = {
                code: code,
                label: magento.label,
                parent_code: parentCode,
                source_parent_code: parentCode,
                sort_order: Number(magento.position || 0),
                source_sort_order: Number(magento.position || 0),
                extension_data: {to_ergonode: {pending_create: true, label: magento.label}}
            };

            if (api.addAndMapCategory(category, magentoId)) {
                category.magento_category_id = magentoId;
                models.categoriesByCode[category.code] = category;
                models.mappedByMagentoId[magentoId] = category;

                return category;
            }

            notify('error', $t('Nie udało się przygotować kategorii do utworzenia w Ergonode.'));
            return false;
        }

        function reportCategoryCollision(magento, existing, code) {
            if (!config.urls.report_category_collision) {
                return;
            }
            jsonPost.post(config.urls.report_category_collision, config, {
                category_tree_id: api.getCategoryTreeId(),
                code: code,
                skipped_magento_category_id: Number(magento.id || 0),
                skipped_label: String(magento.label || ''),
                winning_magento_category_id: Number(existing.magento_category_id || 0),
                winning_label: String(existing.label || existing.code || '')
            }).catch(function () {});
        }

        function nearestMappedParentCode(magento, models) {
            var parentId = Number(magento.parent_id || 0);
            var visited = Object.create(null);
            var mapped;
            var parent;

            while (parentId > 0 && !visited[parentId]) {
                visited[parentId] = true;
                mapped = models.mappedByMagentoId[parentId];
                if (mapped) {
                    return mapped.code;
                }
                parent = models.magentoById[parentId];
                parentId = parent ? Number(parent.parent_id || 0) : 0;
            }

            return null;
        }

        function categoryPathCode(magento, models) {
            var path = [];
            var current = magento;
            var visited = Object.create(null);
            var code = '';
            var segment;

            while (current && models.pathCodes[current.id] === undefined && !visited[current.id]) {
                visited[current.id] = true;
                path.push(current);
                current = models.magentoById[Number(current.parent_id || 0)];
            }
            if (current && visited[current.id]) {
                // A cycle has no shared root: preserve the original per-path fallback.
                path.pop();
                return categoryCodeGenerator.fromPathLabels(path.reverse().map(function (category) {
                    return category.label;
                }));
            }
            if (current) {
                code = models.pathCodes[current.id];
            } else if (path.length) {
                models.pathCodes[path.pop().id] = '';
            }
            while (path.length) {
                current = path.pop();
                segment = categoryCodeGenerator.fromPathLabels([current.label]);
                code = (code && segment ? code + '__' + segment : code || segment).slice(0, 128);
                models.pathCodes[current.id] = code;
            }

            return code;
        }

        function createDraftModels() {
            var models = {
                magentoById: Object.create(null),
                categoriesByCode: Object.create(null),
                mappedByMagentoId: Object.create(null),
                pathCodes: Object.create(null)
            };

            api.getMagentoCategories().forEach(function (category) {
                var id = Number(category.id || 0);

                if (!models.magentoById[id]) {
                    models.magentoById[id] = category;
                }
            });
            api.getCategories().forEach(function (category) {
                var id = Number(category.magento_category_id || 0);

                if (!models.categoriesByCode[category.code]) {
                    models.categoriesByCode[category.code] = category;
                }
                if (id > 0 && !models.mappedByMagentoId[id]) {
                    models.mappedByMagentoId[id] = category;
                }
            });

            return models;
        }
    };

    function createTreeDialog(config, $root) {
        var dialog = document.createElement('div');
        var dialogId = 'vec-create-ergonode-tree-dialog-' + (++treeDialogSequence);
        var titleId = dialogId + '-title';
        var form;
        var codeInput;
        var error;
        var submit;
        var trigger = null;
        var focusTimer;
        var destroyed = false;

        dialog.id = dialogId;
        dialog.className = 'vec-create-ergonode-tree-dialog';
        dialog.hidden = true;
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-labelledby', titleId);
        dialog.innerHTML = '<form data-role="create-ergonode-tree-form">'
            + '<strong class="vec-create-tree-title" id="' + titleId + '">'
            + $t('Utwórz drzewo w Ergonode') + '</strong>'
            + '<label class="vec-create-tree-field"><span>'
            + $t('Kod nowego drzewa (litery, cyfry, _ lub -):')
            + '</span><input name="code" required maxlength="64" pattern="[A-Za-z0-9_-]+" '
            + 'autocomplete="off"></label>'
            + '<label class="vec-create-tree-field"><span>' + $t('Nazwa nowego drzewa:')
            + '</span><input name="name" required maxlength="255" autocomplete="off"></label>'
            + '<div class="vec-create-tree-error" data-role="create-ergonode-tree-error" '
            + 'role="alert" hidden></div>'
            + '<div class="vec-create-tree-actions"><button type="button" class="veui-button" '
            + 'data-role="cancel-create-ergonode-tree">' + $t('Anuluj') + '</button>'
            + '<button type="submit" class="veui-button veui-button-primary">'
            + $t('Utwórz') + '</button></div></form>';
        document.body.appendChild(dialog);
        form = dialog.querySelector('form');
        codeInput = form.elements.code;
        error = dialog.querySelector('[data-role="create-ergonode-tree-error"]');
        submit = form.querySelector('[type="submit"]');

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (submit.disabled || !form.reportValidity()) {
                return;
            }
            submit.disabled = true;
            error.hidden = true;
            jsonPost.post(config.urls.create_tree, config, {
                code: form.elements.code.value,
                name: form.elements.name.value
            }, $t('Nie udało się utworzyć drzewa. Sprawdź sesję API Ergonode.')).then(function (response) {
                if (destroyed) { return; }
                var success = !!(response && response.success);
                var message = response && response.message
                    ? response.message
                    : $t('Nie udało się utworzyć drzewa.');
                var $refresh;

                showTreeStatus(success, message);
                if (success) {
                    $refresh = findTreeControl('refresh-tree-options');
                    close(true);
                    $refresh.trigger('click');
                    return;
                }
                showError(message);
            }).catch(function () {
                if (destroyed) { return; }
                var message = $t('Nie udało się utworzyć drzewa. Sprawdź sesję API Ergonode.');

                showTreeStatus(false, message);
                showError(message);
            }).then(function () {
                submit.disabled = false;
            });
        });
        dialog.querySelector('[data-role="cancel-create-ergonode-tree"]').addEventListener('click', function () {
            close(true);
        });
        dialog.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                close(true);
            }
        });

        return {
            id: dialogId,
            destroy: function () {
                if (destroyed) { return; }
                destroyed = true;
                close(false);
                $(dialog).remove();
            },
            open: function (button) {
                if (destroyed) { return; }
                if (trigger && trigger !== button) {
                    trigger.setAttribute('aria-expanded', 'false');
                }
                trigger = button;
                trigger.setAttribute('aria-expanded', 'true');
                error.hidden = true;
                dialog.hidden = false;
                position();
                window.addEventListener('resize', position);
                window.addEventListener('scroll', position, true);
                document.addEventListener('mousedown', closeFromOutside, true);
                focusTimer = window.setTimeout(function () {
                    position();
                    codeInput.focus();
                }, 0);
            }
        };

        function showError(message) {
            error.textContent = message;
            error.hidden = false;
        }

        function findTreeControl(role) {
            var scope = trigger ? trigger.closest('[data-role="new-mapping-modal"]') : null;

            return (scope ? $(scope) : $root).find('[data-role="' + role + '"]').first();
        }

        function showTreeStatus(success, message) {
            findTreeControl('tree-options-status')
                .prop('hidden', false)
                .toggleClass('is-success', !!success)
                .toggleClass('is-error', !success)
                .text(message);
        }

        function close(returnFocus) {
            window.clearTimeout(focusTimer);
            var focusTarget = trigger;

            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            }
            dialog.hidden = true;
            dialog.removeAttribute('data-placement');
            form.reset();
            error.hidden = true;
            trigger = null;
            window.removeEventListener('resize', position);
            window.removeEventListener('scroll', position, true);
            document.removeEventListener('mousedown', closeFromOutside, true);
            if (returnFocus && focusTarget) {
                focusTarget.focus();
            }
        }

        function closeFromOutside(event) {
            if (!dialog.contains(event.target) && event.target !== trigger) {
                close(false);
            }
        }

        function position() {
            var buttonRect;
            var dialogRect;
            var gap = 12;
            var edge = 16;
            var left;
            var top;
            var placement = 'left';

            if (!trigger || dialog.hidden) {
                return;
            }
            buttonRect = trigger.getBoundingClientRect();
            dialogRect = dialog.getBoundingClientRect();
            left = buttonRect.left - dialogRect.width - gap;
            if (left < edge) {
                left = buttonRect.right + gap;
                placement = 'right';
            }
            if (left + dialogRect.width > window.innerWidth - edge) {
                left = Math.max(edge, Math.min(
                    buttonRect.right - dialogRect.width,
                    window.innerWidth - dialogRect.width - edge
                ));
                placement = 'below';
            }
            top = placement === 'below'
                ? buttonRect.bottom + gap
                : buttonRect.top + (buttonRect.height - dialogRect.height) / 2;
            top = Math.max(edge, Math.min(top, window.innerHeight - dialogRect.height - edge));
            dialog.style.left = Math.round(left) + 'px';
            dialog.style.top = Math.round(top) + 'px';
            dialog.setAttribute('data-placement', placement);
        }
    }

});
