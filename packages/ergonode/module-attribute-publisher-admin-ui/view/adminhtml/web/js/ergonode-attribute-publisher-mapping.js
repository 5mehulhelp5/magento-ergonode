define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate',
    'Ergonode_CoreAdminUi/js/buttons',
    'Ergonode_CoreAdminUi/js/request',
    'Ergonode_CoreAdminUi/js/bulk-publish-progress',
    'Ergonode_CoreAdminUi/js/text',
    'Ergonode_CoreAdminUi/js/mapping-elements'
], function (
    $, modal, $t, buttons, request, createBulkPublishProgress, text, mappingElements
) {
    'use strict';

    var supportedMagentoAttributeTypes = [
        'boolean', 'date', 'decimal', 'file', 'image', 'multiselect', 'select', 'text', 'textarea', 'unit'
    ];
    var supportedErgonodeAttributeTypes = [
        'date', 'file', 'image', 'multiselect', 'numeric', 'select', 'text', 'textarea', 'unit'
    ];
    function typeClass(type) {
        return 'vea-type-' + text.normalize(type).replace(/[^a-z0-9_-]/g, '-');
    }

    function supportedMagentoAttributeType(type) {
        return supportedMagentoAttributeTypes.indexOf((type || '').toLowerCase()) !== -1;
    }

    function suggestedErgonodeAttributeType(type) {
        type = (type || '').toLowerCase();

        if (type === 'boolean') {
            return 'select';
        }
        if (type === 'decimal') {
            return 'numeric';
        }

        return type;
    }

    function hasActiveLanguageMapping(config) {
        return !config || !config.language_mapping || config.language_mapping.active !== false;
    }

    function showLanguageMappingRequired(root, config) {
        var languageMapping = config && config.language_mapping ? config.language_mapping : {};

        root.veaContext.message.error(
            $t('Nie udało się odświeżyć atrybutów'),
            {
                message: languageMapping.message || $t(
                    'Configure at least one active Ergonode language mapped to an active Magento store scope.'
                )
            },
            $t('Nie udało się wczytać atrybutów z Ergonode.'),
            {
                label: $t('Przejdź do mapowania języków'),
                url: languageMapping.url || ''
            }
        );
    }

    function allowedErgonodeAttributeTypes(magentoType, compatibility) {
        return supportedErgonodeAttributeTypes.filter(function (ergonodeType) {
            return ((compatibility || {})[ergonodeType] || []).indexOf(
                (magentoType || '').toLowerCase()
            ) !== -1;
        });
    }

    function pendingTypePickerHtml(type, magentoType, compatibility) {
        var allowedTypes = allowedErgonodeAttributeTypes(magentoType, compatibility);

        if (allowedTypes.length < 2) {
            return mappingElements.typeBadgeHtml(type);
        }

        return [
            '<button type="button" class="vea-type-badge vea-pending-type-trigger ',
            text.escapeHtml(typeClass(type)),
            '" data-role="pending-ergonode-type-trigger" data-magento-type="',
            text.escapeHtml(magentoType),
            '" aria-haspopup="dialog" aria-label="',
            text.escapeHtml($t('Zmień typ tworzonego atrybutu Ergonode')),
            '">',
            text.escapeHtml(type),
            '</button>'
        ].join('');
    }

    function pendingTypeOptionsHtml(allowedTypes, selectedType) {
        return allowedTypes.map(function (allowedType) {
            return [
                '<button type="button" class="vea-type-badge vea-pending-type-option ',
                text.escapeHtml(typeClass(allowedType)),
                '" data-role="pending-ergonode-type-option" data-type="',
                text.escapeHtml(allowedType),
                '" aria-pressed="',
                allowedType === selectedType ? 'true' : 'false',
                '">',
                text.escapeHtml(allowedType),
                '</button>'
            ].join('');
        }).join('');
    }

    function buttonHtml(mode) {
        var createLabel = mode === 'option'
            ? $t('Utwórz opcję w Ergonode z Magento')
            : $t('Utwórz atrybut w Ergonode z Magento');
        var hiddenLabel = mode === 'option'
            ? $t('Utwórz opcję w Ergonode')
            : $t('Utwórz atrybut w Ergonode');

        return [
            '<button type="button" class="vea-create-option vea-create-ergonode" data-role="create-ergonode-',
            mode,
            '" data-completes-missing-side="1" title="',
            text.escapeHtml(createLabel),
            '" aria-label="',
            text.escapeHtml(createLabel),
            '">',
            '<span class="veui-create-ergonode-icon" aria-hidden="true"></span>',
            '<span class="vea-visually-hidden">',
            text.escapeHtml(hiddenLabel),
            '</span>',
            '</button>'
        ].join('');
    }

    function rowSlots(row) {
        return {
            ergo: row ? row.querySelector('[data-role="pair-slot"][data-side="ergo"]') : null,
            magento: row ? row.querySelector('[data-role="pair-slot"][data-side="magento"]') : null
        };
    }

    function isEligibleRow(row, mode) {
        var slots = rowSlots(row);
        var source = mappingElements.slotPayload(slots.magento);

        return !!(slots.ergo && !slots.ergo.getAttribute('data-code') && source &&
            (mode !== 'attribute' || supportedMagentoAttributeType(source.type)));
    }

    function addActions(root, mode) {
        root.querySelectorAll('[data-role="mapping-row"]').forEach(function (row) {
            var slots = rowSlots(row);

            if (!isEligibleRow(row, mode) ||
                slots.ergo.querySelector('[data-role="create-ergonode-' + mode + '"]')) {
                return;
            }

            slots.ergo.insertAdjacentHTML('beforeend', buttonHtml(mode));
        });
    }

    function pendingPayload(source, mode) {
        return {
            label: source.label || source.code,
            code: source.code,
            type: mode === 'option' ? 'option' : suggestedErgonodeAttributeType(source.type),
            scope: source.scope,
            pending_create: true
        };
    }

    function fillErgonodeSlot(slot, payload, mode, sourceType, compatibility) {
        var meta = [
            '<span class="vea-card-subline"><code>',
            text.escapeHtml(payload.code),
            '</code>',
            mode === 'attribute'
                ? pendingTypePickerHtml(payload.type, sourceType, compatibility)
                : mappingElements.typeBadgeHtml(payload.type),
            '<span class="vea-scope">',
            text.escapeHtml(payload.scope),
            '</span>',
            mappingElements.pendingBadgeHtml(),
            '</span>'
        ];

        if (mode === 'attribute') {
            meta.push(
                '<button type="button" class="vea-unlink" data-role="unlink-mapping" data-side="ergo" ',
                'title="', text.escapeHtml($t('Usuń mapowanie elementu Ergonode')), '" aria-label="',
                text.escapeHtml($t('Usuń mapowanie elementu Ergonode: %1').replace('%1', payload.label)),
                '"><span aria-hidden="true"></span></button>'
            );
        }

        slot.classList.remove('is-empty');
        slot.classList.add('has-unlink-action');
        slot.setAttribute('data-label', payload.label);
        slot.setAttribute('data-code', payload.code);
        slot.setAttribute('data-type', payload.type);
        slot.setAttribute('data-scope', payload.scope);
        slot.setAttribute('data-pending-create', '1');
        slot.setAttribute('data-create-label', payload.label);
        slot.setAttribute('draggable', 'true');
        slot.innerHTML = '<strong>' + text.escapeHtml(payload.label) + '</strong>' + meta.join('');
    }

    function markRowPending(row) {
        var status = row.querySelector('[data-role="mapping-status"]');

        row.classList.remove('vea-status-tone-ok', 'vea-status-tone-error', 'is-type-error');
        row.classList.add('vea-status-tone-warning');
        row.setAttribute('data-status-tone', 'warning');
        if (status) {
            status.className = 'vea-status vea-status-warning';
            status.setAttribute('title', $t('Mapowanie oczekuje na zapis'));
            status.setAttribute('aria-label', $t('Mapowanie oczekuje na zapis'));
        }
    }

    function selectPendingType(trigger, selectedType, compatibility) {
        var slot = trigger ? trigger.closest('[data-role="pair-slot"][data-side="ergo"]') : null;
        var magentoType = trigger ? trigger.getAttribute('data-magento-type') || '' : '';
        var previousType = slot ? slot.getAttribute('data-type') || '' : '';

        if (!slot || slot.getAttribute('data-pending-create') !== '1' || !selectedType ||
            allowedErgonodeAttributeTypes(magentoType, compatibility).indexOf(selectedType) === -1) {
            return;
        }

        slot.setAttribute('data-type', selectedType);
        trigger.classList.remove(typeClass(previousType));
        trigger.classList.add(typeClass(selectedType));
        trigger.textContent = selectedType;
    }

    function typeModal(root) {
        var modalElement;
        var state;

        if (root.veaPendingErgonodeTypeModal) {
            return root.veaPendingErgonodeTypeModal;
        }

        modalElement = document.createElement('div');
        modalElement.setAttribute('data-role', 'pending-ergonode-type-modal');
        modalElement.innerHTML = '<div class="vea-pending-type-modal-options" ' +
            'data-role="pending-ergonode-type-options"></div>';
        document.body.appendChild(modalElement);
        state = {
            element: modalElement,
            trigger: null,
            widget: $(modalElement)
        };

        modal({
            type: 'popup',
            responsive: true,
            innerScroll: true,
            modalClass: 'vea-pending-type-modal',
            title: $t('Wybierz typ atrybutu Ergonode'),
            buttons: []
        }, state.widget);
        state.onClick = function (event) {
            var option = event.target.closest('[data-role="pending-ergonode-type-option"]');

            if (!option || !modalElement.contains(option) || !state.trigger) {
                return;
            }

            event.preventDefault();
            selectPendingType(state.trigger, option.getAttribute('data-type') || '', root.veaAttributeCompatibility);
            state.widget.modal('closeModal');
        };
        modalElement.addEventListener('click', state.onClick);
        root.veaPendingErgonodeTypeModal = state;

        return state;
    }

    function openTypeModal(root, trigger) {
        var slot = trigger.closest('[data-role="pair-slot"][data-side="ergo"]');
        var magentoType = trigger.getAttribute('data-magento-type') || '';
        var allowedTypes = allowedErgonodeAttributeTypes(magentoType, root.veaAttributeCompatibility);
        var state;
        var options;

        if (!slot || slot.getAttribute('data-pending-create') !== '1' || allowedTypes.length < 2) {
            return;
        }

        state = typeModal(root);
        state.trigger = trigger;
        options = state.element.querySelector('[data-role="pending-ergonode-type-options"]');
        options.innerHTML = pendingTypeOptionsHtml(allowedTypes, slot.getAttribute('data-type') || '');
        state.widget.modal('openModal');
    }

    function createMissingForRow(row, mode, compatibility) {
        var slots = rowSlots(row);
        var source = mappingElements.slotPayload(slots.magento);

        if (!isEligibleRow(row, mode) || !source) {
            return false;
        }

        fillErgonodeSlot(slots.ergo, pendingPayload(source, mode), mode, source.type, compatibility);
        markRowPending(row);

        return true;
    }

    function progressOptions(entityKind) {
        if (entityKind === 'option') {
            return {
                title: $t('Tworzenie opcji w Ergonode'),
                progressLabel: $t('Postęp tworzenia opcji'),
                unit: $t('opcji'),
                processingBatch: $t('Przetwarzam paczkę %1 z %2 (%3 opcji).'),
                complete: $t('Wszystkie opcje zostały utworzone i zapisane.'),
                partial: $t(
                    'Proces zakończony. Poprawne opcje zapisano, a błędy pozostawiono do ponowienia.'
                )
            };
        }
        if (entityKind === 'category_attribute') {
            return {
                title: $t('Tworzenie atrybutów kategorii w Ergonode'),
                progressLabel: $t('Postęp tworzenia atrybutów kategorii'),
                unit: $t('atrybutów kategorii'),
                processingBatch: $t('Przetwarzam paczkę %1 z %2 (%3 atrybutów kategorii).'),
                complete: $t('Wszystkie atrybuty kategorii zostały utworzone i zapisane.'),
                partial: $t(
                    'Proces zakończony. Poprawne atrybuty kategorii zapisano, a błędy pozostawiono do ponowienia.'
                )
            };
        }

        return {
            title: $t('Tworzenie atrybutów w Ergonode'),
            progressLabel: $t('Postęp tworzenia atrybutów'),
            unit: $t('atrybutów'),
            processingBatch: $t('Przetwarzam paczkę %1 z %2 (%3 atrybutów).'),
            complete: $t('Wszystkie atrybuty zostały utworzone i zapisane.'),
            partial: $t(
                'Proces zakończony. Poprawne atrybuty zapisano, a błędy pozostawiono do ponowienia.'
            )
        };
    }

    function pendingPublishItems(root) {
        return Array.prototype.map.call(
            root.querySelectorAll('[data-role="mapping-row"]'),
            function (row) {
                var slots = rowSlots(row);
                var source = mappingElements.slotPayload(slots.magento);

                if (!slots.ergo || slots.ergo.getAttribute('data-pending-create') !== '1' || !source) {
                    return null;
                }

                return {
                    code: source.code,
                    label: source.label || source.code,
                    type: source.type,
                    scope: source.scope,
                    target_type: slots.ergo.getAttribute('data-type') || '',
                    row: row,
                    slot: slots.ergo
                };
            }
        ).filter(Boolean);
    }

    function publishPayload(item) {
        return {
            code: item.code,
            label: item.label,
            type: item.type,
            scope: item.scope,
            target_type: item.target_type
        };
    }

    function sendPublishBatch(root, config, items) {
        return new Promise(function (resolve, reject) {
            $.ajax({
                url: config.urls ? config.urls.batch_create : '',
                type: 'POST',
                dataType: 'json',
                timeout: 60000,
                data: {
                    form_key: config.form_key || window.FORM_KEY || '',
                    attribute_mapping_id: root.veaConfig && root.veaConfig.attribute_mapping_id
                        ? root.veaConfig.attribute_mapping_id
                        : 0,
                    items: JSON.stringify(items.map(publishPayload))
                }
            }).done(resolve).fail(function (xhr) {
                reject(new Error(
                    xhr && xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : $t('Nie udało się połączyć z Magento podczas przetwarzania paczki.')
                ));
            });
        });
    }

    function successfulResult(result) {
        return ['failed', 'blocked', 'skipped'].indexOf(String(result.status || '')) === -1;
    }

    function applyPreparedMapping(item, result) {
        var mapping = result && result.mapping ? result.mapping : {};
        var slot = item.slot;
        var pendingBadge;

        if (!slot || !successfulResult(result)) {
            return;
        }
        slot.setAttribute('data-code', mapping.code || item.code);
        slot.setAttribute('data-label', mapping.label || item.label);
        slot.setAttribute('data-type', mapping.type || item.target_type || item.type);
        slot.setAttribute('data-scope', mapping.scope || item.scope);
        slot.setAttribute('data-pending-create', '0');
        slot.setAttribute('data-create-label', '');
        pendingBadge = slot.querySelector('[data-role="pending-badge"]');
        if (pendingBadge) {
            pendingBadge.remove();
        }
    }

    function showFailedOption(item, result) {
        var row = item.row;
        var slot = item.slot;
        var message = row && row.querySelector('[data-role="pair-message"]');

        if (!row || !slot) {
            return;
        }
        slot.classList.remove('has-unlink-action');
        slot.classList.add('is-empty');
        slot.setAttribute('data-code', '');
        slot.setAttribute('data-label', '');
        slot.setAttribute('data-type', '');
        slot.setAttribute('data-scope', '');
        slot.setAttribute('data-pending-create', '0');
        slot.setAttribute('data-create-label', '');
        slot.removeAttribute('draggable');
        slot.innerHTML = '<span class="vea-slot-empty-line"><span class="vea-slot-drop-icon" '
            + 'aria-hidden="true"></span><strong>' + text.escapeHtml($t('Przeciągnij opcję')) + '</strong></span>'
            + mappingElements.typeBadgeHtml(item.type, 'slot-hint');
        row.classList.remove('vea-status-tone-ok', 'vea-status-tone-warning');
        row.classList.add('vea-status-tone-error');
        row.setAttribute('data-status-tone', 'error');
        if (message) {
            message.textContent = String(result.message || $t('Nie udało się utworzyć opcji w Ergonode.'));
            message.hidden = false;
        }
        if (row.parentNode) {
            row.parentNode.insertBefore(row, row.parentNode.firstChild);
        }
    }

    function normalizeBatchResults(items, response) {
        var resultByCode = {};

        (response.items || []).forEach(function (item) {
            resultByCode[String(item.code || '')] = item;
        });

        return items.map(function (item) {
            return resultByCode[item.code] || {
                code: item.code,
                label: item.label,
                status: 'failed',
                message: $t('Backend nie zwrócił wyniku dla elementu.')
            };
        });
    }

    function processPublishQueue(root, config, progress, queue) {
        var batchSize = Math.max(1, Math.min(50, Number(config.batch_size || 20)));
        var batchCount = Math.ceil(queue.length / batchSize);
        var failed = 0;

        return processAt(0).then(function () {
            return {failed: failed};
        });

        function processAt(offset) {
            var batch;

            if (offset >= queue.length) {
                return Promise.resolve();
            }
            batch = queue.slice(offset, offset + batchSize);
            progress.showBatch(Math.floor(offset / batchSize) + 1, batchCount, batch);

            return sendPublishBatch(root, config, batch).then(function (response) {
                var results;

                if (!response || !response.success) {
                    if (response && Number(response.retry_after_seconds || 0) > 0) {
                        return progress.wait(
                            Number(response.retry_after_seconds),
                            response.message || $t('Ergonode ograniczyło liczbę zapytań.')
                        ).then(function () {
                            return processAt(offset);
                        });
                    }
                    throw new Error(response && response.message
                        ? response.message
                        : $t('Nie udało się przetworzyć paczki.'));
                }
                results = normalizeBatchResults(batch, response);
                results.forEach(function (result, index) {
                    if (successfulResult(result)) {
                        applyPreparedMapping(batch[index], result);
                    } else {
                        failed++;
                        if (config.mode === 'option') {
                            showFailedOption(batch[index], result);
                        }
                    }
                });
                progress.applyBatch(results);

                return processAt(offset + batch.length);
            });
        }
    }

    function payloadWithoutPendingCreates(payload) {
        payload = $.extend(true, {}, payload || {});
        payload.mappings = (payload.mappings || []).map(function (mapping) {
            if (mapping.left && mapping.left.pending_create) {
                return {left: null, right: mapping.right || null};
            }

            return mapping;
        });

        return payload;
    }

    function savePreparedMappings(root, hasFailures) {
        var pageConfig = root.veaConfig || {};
        var context = root.veaContext || {};
        var payload;

        if (typeof context.serialize !== 'function') {
            return Promise.reject(new Error($t('Brak kontraktu zapisu mapowania.')));
        }
        payload = context.serialize();
        if (hasFailures) {
            payload = payloadWithoutPendingCreates(payload);
        }

        return request.post(pageConfig.urls ? pageConfig.urls.save : '', pageConfig, {
            payload: JSON.stringify(payload)
        }).then(function (response) {
            if (!hasFailures && context.dirty) {
                context.dirty.capture();
            }

            return response;
        });
    }

    function runBulkPublish(root, config, progress, saveButton) {
        var queue = pendingPublishItems(root);

        progress.open(queue.length);
        buttons.setBusy(saveButton, true, {busyClass: 'is-working'});

        return processPublishQueue(root, config, progress, queue).then(function (outcome) {
            progress.finalizing();

            return savePreparedMappings(root, outcome.failed > 0).then(function () {
                progress.complete(outcome.failed > 0, outcome.failed > 0 ? null : function () {
                    window.location.reload();
                });
            });
        }).catch(function (error) {
            progress.fail(error && error.message
                ? error.message
                : $t('Nie udało się zakończyć procesu tworzenia w Ergonode.'));
        }).finally(function () {
            buttons.setBusy(saveButton, false, {busyClass: 'is-working'});
        });
    }

    function syncActions(root, mode) {
        addActions(root, mode);
    }

    return function (config, element) {
        var mode = config && config.mode === 'option' ? 'option' : 'attribute';
        var progress = createBulkPublishProgress(progressOptions(config && config.entity_kind));
        var publishInProgress = false;
        var observer;

        function interceptSave(event) {
            var saveButton = event.target.closest('[data-role="save-mapping"]');

            if (!saveButton || !element.contains(saveButton) || publishInProgress
                || pendingPublishItems(element).length === 0) {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            publishInProgress = true;
            runBulkPublish(element, config || {}, progress, saveButton).finally(function () {
                publishInProgress = false;
            });
        }

        function handleClick(event) {
            var typeTrigger = event.target.closest('[data-role="pending-ergonode-type-trigger"]');
            var button = event.target.closest('[data-role="create-ergonode-' + mode + '"]');
            var row;

            if (typeTrigger && element.contains(typeTrigger)) {
                event.preventDefault();
                event.stopPropagation();
                openTypeModal(element, typeTrigger);
                return;
            }

            if (!button || !element.contains(button)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            if (!hasActiveLanguageMapping(config)) {
                showLanguageMappingRequired(element, config);
                return;
            }
            row = button.closest('[data-role="mapping-row"]');
            createMissingForRow(row, mode, element.veaAttributeCompatibility);
        }

        element.veaAttributeCompatibility = config && config.attribute_compatibility || {};
        syncActions(element, mode);
        element.addEventListener('click', interceptSave, true);
        element.addEventListener('click', handleClick);
        if (typeof MutationObserver !== 'undefined') {
            observer = new MutationObserver(function () {
                syncActions(element, mode);
            });
            observer.observe(element, {attributes: true, childList: true, subtree: true});
        }
        if (element.veaWorkspace && typeof element.veaWorkspace.cleanup === 'function') {
            element.veaWorkspace.cleanup(function () {
                var state = element.veaPendingErgonodeTypeModal;
                var shell;
                var transitionEvent;

                element.removeEventListener('click', interceptSave, true);
                element.removeEventListener('click', handleClick);
                if (state) {
                    shell = state.element.closest('.modal-popup');
                    if (state.widget.modal('option', 'isOpen') === true) {
                        state.widget.modal('option', 'transitionEvent', null);
                        state.widget.modal('closeModal');
                    } else {
                        transitionEvent = state.widget.modal('option', 'transitionEvent');
                        if (transitionEvent) {
                            $(shell).triggerHandler(transitionEvent);
                        }
                    }
                    state.element.removeEventListener('click', state.onClick);
                    state.widget.modal('destroy');
                    $(shell || state.element).remove();
                    state.trigger = null;
                    delete element.veaPendingErgonodeTypeModal;
                }
                if (typeof progress.destroy === 'function') {
                    progress.destroy();
                }
                if (observer) {
                    observer.disconnect();
                }
            });
        }
    };
});
