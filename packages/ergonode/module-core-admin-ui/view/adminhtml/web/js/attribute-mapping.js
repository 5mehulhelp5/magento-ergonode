define([
    'Ergonode_CoreAdminUi/js/attribute-option-navigation',
    'Ergonode_CoreAdminUi/js/attribute-refresh-result',
    'Ergonode_CoreAdminUi/js/mapping-save-result',
    'Ergonode_CoreAdminUi/js/mapping-board',
    'Ergonode_CoreAdminUi/js/complete-missing',
    'Ergonode_CoreAdminUi/js/workspace',
    'Ergonode_CoreAdminUi/js/text',
    'Ergonode_CoreAdminUi/js/mapping-elements',
    'Ergonode_CoreAdminUi/js/workspace-context',
    'Ergonode_CoreAdminUi/js/buttons',
    'Ergonode_CoreAdminUi/js/synchronization-actions',
    'Ergonode_CoreAdminUi/js/request',
    'Ergonode_CoreAdminUi/js/search',
    'Ergonode_CoreAdminUi/js/drag-drop',
    'Ergonode_CoreAdminUi/js/source-bulk-transfer',
    'Ergonode_CoreAdminUi/js/mapping-requirements',
    'Ergonode_CoreAdminUi/js/snapshot-removal',
    'Ergonode_CoreAdminUi/js/unsaved-navigation',
    'Ergonode_CoreAdminUi/js/option-mapping-modal',
    'mage/translate'
], function (optionNavigation, refreshResult, mappingSaveResult, mappingBoard, completeMissing, workspace, text, mappingElements, workspaceContext, buttons, synchronizationActions, request, search, dragDrop, sourceBulkTransfer, mappingRequirements, snapshotRemoval, unsavedNavigation, optionMappingModal, $t) {
    'use strict';

    var dragMimeType = 'application/vnd.ergonode.attribute+json';
    var getSlotSide = mappingBoard.getSlotSide;
    var getOppositeSlot = mappingBoard.getOppositeSlot;
    var getSlotType = mappingBoard.getSlotType;
    var hasSlotAttribute = mappingBoard.hasSlotValue;
    var getStatusTone = mappingBoard.getStatusTone;
    var updateRowSearchData = mappingBoard.updateRowSearchData;
    var showRowMessage = mappingBoard.showRowMessage;
    var hideRowMessage = mappingBoard.hideRowMessage;
    var isRowEmpty = mappingBoard.isRowEmpty;
    var acceptsMappedDrop = mappingBoard.acceptsMappedDrop;
    var acceptsSourceDrop = mappingBoard.acceptsSourceDrop;
    var clearCreatePanelStates = mappingBoard.clearCreatePanelStates;
    var clearDisablePanelStates = mappingBoard.clearDisablePanelStates;
    var clearSlotDropState = mappingBoard.clearSlotDropState;
    var setCreatePanelActive = mappingBoard.setCreatePanelActive;
    var setCreatePanelReady = mappingBoard.setCreatePanelReady;
    var setDisablePanelActive = mappingBoard.setDisablePanelActive;
    var setDisablePanelReady = mappingBoard.setDisablePanelReady;
    var setMappedDragState = mappingBoard.setMappedDragState;
    var attributeTypeCompatibility = {};
    var magentoAttributeTypeConstraints = {};

    function configureAttributeTypeCompatibility(compatibility) {
        var normalized = {};

        Object.keys(compatibility || {}).forEach(function (ergonodeType) {
            var normalizedType = text.normalize(ergonodeType);

            if (!normalizedType || !Array.isArray(compatibility[ergonodeType])) {
                return;
            }

            normalized[normalizedType] = compatibility[ergonodeType].map(text.normalize).filter(Boolean);
        });

        attributeTypeCompatibility = normalized;
    }

    function configureMagentoAttributeTypeConstraints(constraints) {
        var normalized = {};

        Object.keys(constraints || {}).forEach(function (attributeCode) {
            var normalizedCode = text.normalize(attributeCode);

            if (!normalizedCode || !Array.isArray(constraints[attributeCode])) {
                return;
            }

            normalized[normalizedCode] = constraints[attributeCode].map(text.normalize).filter(Boolean);
        });

        magentoAttributeTypeConstraints = normalized;
    }

    function canMapAttributeTypes(leftType, rightType, magentoAttributeCode) {
        var allowedErgonodeTypes;

        leftType = text.normalize(leftType);
        rightType = text.normalize(rightType);
        magentoAttributeCode = text.normalize(magentoAttributeCode);

        if (!leftType || !rightType) {
            return true;
        }

        if ((attributeTypeCompatibility[leftType] || []).indexOf(rightType) === -1) {
            return false;
        }

        allowedErgonodeTypes = magentoAttributeTypeConstraints[magentoAttributeCode];

        return !allowedErgonodeTypes || allowedErgonodeTypes.indexOf(leftType) !== -1;
    }

    function canCreateMagentoAttribute(root, type) {
        type = text.normalize(type);

        return !!(root && root.veaConfig && root.veaConfig.allow_magento_attribute_creation)
            && ['gallery', 'relation', ''].indexOf(type) === -1;
    }

    function hasExistingMagentoAttribute(root, code) {
        var existingCodes = root && root.veaConfig
            ? root.veaConfig.existing_magento_attribute_codes
            : [];
        var normalizedCode = text.normalize(code);

        return normalizedCode !== '' && Array.isArray(existingCodes) && existingCodes.some(function (existingCode) {
            return text.normalize(existingCode) === normalizedCode;
        });
    }

    function existingMagentoAttributeMessage(code) {
        return $t('Atrybut Magento o kodzie „%1” już istnieje.').replace('%1', code || '');
    }

    function createMagentoAttributeAction(root, card, active) {
        var code = card ? card.getAttribute('data-code') || '' : '';
        var exists = hasExistingMagentoAttribute(root, code);
        var label = $t('Utwórz w Magento');
        var unavailableMessage = existingMagentoAttributeMessage(code);

        return {
            active: active,
            available: !exists,
            className: exists ? 'is-existing-magento-attribute' : '',
            iconClass: exists ? 'vea-existing-magento-attribute-icon' : 'vea-create-option-icon',
            label: label,
            requiresActive: true,
            role: 'entity-create-magento-attribute',
            title: exists ? unavailableMessage : label,
            ariaLabel: exists ? label + '. ' + unavailableMessage : label
        };
    }

    function setupErgonodeEntityOptions(root) {
        root.querySelectorAll(
            '[data-role="attribute-side"][data-source-panel="ergo"] [data-role="entity-card"]'
        ).forEach(function (card) {
            var activeToggle = card.querySelector('[data-role="attribute-active-toggle"]');
            var active = activeToggle ? buttons.isPressed(activeToggle) : true;
            var label = card.getAttribute('data-label') || card.getAttribute('data-code') || '';
            var actions = [];

            if (canCreateMagentoAttribute(root, card.getAttribute('data-type') || '')) {
                actions.push(createMagentoAttributeAction(root, card, active));
            }
            snapshotRemoval.enhance(card, {
                active: active,
                actions: actions,
                removable: !!(root.veaConfig && root.veaConfig.urls && root.veaConfig.urls.delete_snapshot),
                mappingAction: true,
                menuLabel: $t('Opcje atrybutu') + ': ' + label,
                snapshot: {
                    code: card.getAttribute('data-code') || '',
                    label: label
                },
                toggle: activeToggle
            });
        });
    }

    function languageMappingAction(config, error) {
        if (!error || error.message !== $t('Configure at least one active Ergonode language mapped to an active Magento store scope.')) {
            return null;
        }

        return {
            label: $t('Przejdź do mapowania języków'),
            url: config.urls && config.urls.language_mapping
        };
    }

    function compatibleAttributeTypes(side, requiredType, magentoAttributeCode) {
        var normalizedType = text.normalize(requiredType);
        var types;

        if (!normalizedType) {
            return [];
        }

        types = side === 'magento'
            ? (attributeTypeCompatibility[normalizedType] || []).slice()
            : Object.keys(attributeTypeCompatibility).filter(function (type) {
                return canMapAttributeTypes(type, normalizedType, magentoAttributeCode);
            });

        return types.sort(function (leftType, rightType) {
            if (leftType === normalizedType) {
                return -1;
            }

            if (rightType === normalizedType) {
                return 1;
            }

            return leftType.localeCompare(rightType);
        });
    }

    function magentoAttributeTypeForErgonode(type) {
        type = text.normalize(type);

        return type;
    }

    function canDropOnSlot(slot, payloadType, payloadCode) {
        var side = getSlotSide(slot);
        var opposite = getOppositeSlot(slot);
        var oppositeType = getSlotType(opposite);
        var magentoAttributeCode = side === 'magento'
            ? payloadCode
            : (opposite ? opposite.getAttribute('data-code') || '' : '');

        if (!oppositeType) {
            return true;
        }

        return side === 'magento'
            ? canMapAttributeTypes(oppositeType, payloadType, magentoAttributeCode)
            : canMapAttributeTypes(payloadType, oppositeType, magentoAttributeCode);
    }

    function unlinkMappingHtml() {
        var label = $t('Usuń mapowanie atrybutów');

        return [
            '<button type="button" class="vea-link-indicator vea-unlink-mapping" ',
            'data-role="unlink-mapping" title="',
            text.escapeHtml(label),
            '" aria-label="',
            text.escapeHtml(label),
            '">',
            '<span aria-hidden="true"></span>',
            '</button>'
        ].join('');
    }

    function attributeCardHtml(payload) {
        return [
            '<span class="vea-drag-handle" aria-hidden="true"></span>',
            '<div class="vea-card-copy">',
            '<strong>',
            text.escapeHtml(payload.label),
            '</strong>',
            mappingElements.metaHtml(payload),
            '</div>',
            '<button type="button" class="vea-card-toggle" data-role="attribute-active-toggle" aria-label="',
            text.escapeHtml($t('Atrybut aktywny do mapowania')),
            '" aria-pressed="true" title="',
            text.escapeHtml($t('Włącz / wyłącz mapowanie')),
            '">',
            '<span aria-hidden="true"></span>',
            '</button>'
        ].join('');
    }

    function buildPendingMagentoAttribute(leftPayload) {
        var label = leftPayload.label || leftPayload.code || '';

        return {
            label: label,
            code: leftPayload.code || '',
            type: magentoAttributeTypeForErgonode(leftPayload.type),
            scope: leftPayload.scope || 'global',
            source: 'magento',
            pending_create: true,
            create_label: label
        };
    }

    function emptySlotHtml(side, requiredType, root, attributeCode) {
        var hint = side === 'magento' ? $t('brakuje targetu') : $t('brakuje źródła');

        if (requiredType) {
            var compatibleTypes = compatibleAttributeTypes(side, requiredType, attributeCode);
            var html = [
                '<strong>' + text.escapeHtml($t('Zgodne typy')) + '</strong>',
                '<span class="vea-compatible-types" data-role="slot-hint">',
                compatibleTypes.map(function (type) {
                    return mappingElements.typeBadgeHtml(type);
                }).join(''),
                '</span>'
            ];

            if (side === 'magento' && canCreateMagentoAttribute(root, requiredType)) {
                var attributeExists = hasExistingMagentoAttribute(root, attributeCode);
                var label = $t('Utwórz atrybut w Magento z atrybutu Ergonode');
                var title = attributeExists ? existingMagentoAttributeMessage(attributeCode) : label;

                html.push(
                    '<button type="button" class="vea-create-option vea-create-attribute' + (attributeExists ? ' is-existing-magento-attribute' : '') + '" data-role="create-magento-attribute" data-completes-missing-side="1"' + (attributeExists ? ' disabled' : '') + ' title="' + text.escapeHtml(title) + '" aria-label="' + text.escapeHtml(title) + '">',
                    '<span class="' + (attributeExists ? 'vea-existing-magento-attribute-icon' : 'vea-create-option-icon') + '" aria-hidden="true"></span>',
                    '<span class="vea-visually-hidden">' + text.escapeHtml($t('Utwórz atrybut w Magento')) + '</span>',
                    '</button>'
                );
            }

            return html.join('');
        }

        return '<strong>' + text.escapeHtml($t('Upuść atrybut')) + '</strong><span data-role="slot-hint">' + text.escapeHtml(hint) + '</span>';
    }

    function findMappedSlot(root, source, code, ignoredSlot) {
        var result = null;
        var normalizedCode = text.normalize(code);

        if (!root || !source || !normalizedCode) {
            return null;
        }

        root.querySelectorAll('[data-role="pair-slot"][data-side="' + source + '"]').forEach(function (slot) {
            if (result || slot === ignoredSlot) {
                return;
            }

            if (text.normalize(slot.getAttribute('data-code')) === normalizedCode) {
                result = slot;
            }
        });

        return result;
    }

    function isPayloadAlreadyMapped(root, payload, ignoredSlot) {
        return !!(payload && findMappedSlot(root, payload.source, payload.code, ignoredSlot));
    }

    function getSourceCardPayload(card) {
        var activeToggle = card.querySelector('[data-role="attribute-active-toggle"]');
        var isActive = activeToggle ? buttons.isPressed(activeToggle) : true;
        var isMapped = card.getAttribute('data-mapped') === '1';
        var root = card.closest('.vea-mapping');
        var payload;

        if (!isActive || isMapped) {
            return null;
        }

        payload = mappingElements.cardPayload(card);

        if (isPayloadAlreadyMapped(root, payload)) {
            setAttributeMapped(root, payload.source, payload.code, true);
            return null;
        }

        payload.origin = 'source';

        return payload;
    }

    function findAttributeCard(root, source, code) {
        var result = null;

        root.querySelectorAll('[data-role="entity-card"]').forEach(function (card) {
            if (result) {
                return;
            }

            if (card.getAttribute('data-source') === source && card.getAttribute('data-code') === code) {
                result = card;
            }
        });

        return result;
    }

    function setAttributeMapped(root, source, code, mapped) {
        var card = findAttributeCard(root, source, code);
        var panel;

        if (!card) {
            return;
        }

        if (!mapped && card.getAttribute('data-pending-create') === '1') {
            panel = card.closest('[data-role="attribute-side"]');
            card.remove();
            if (panel) {
                updateSidePanel(panel);
            }
            return;
        }

        card.setAttribute('data-mapped', mapped ? '1' : '0');
        card.classList.toggle('is-mapped', mapped);
        panel = card.closest('[data-role="attribute-side"]');

        if (panel) {
            updateSidePanel(panel);
        }
    }

    function appendMagentoAttributeCard(root, payload) {
        var panel = root.querySelector('[data-role="attribute-side"][data-source-panel="magento"]');
        var list = panel ? panel.querySelector('[data-role="attribute-list"]') : null;
        var card = findAttributeCard(root, 'magento', payload.code);

        if (!panel || !list || !payload || !payload.code) {
            return null;
        }

        if (card && card.getAttribute('data-pending-create') !== '1') {
            return card;
        }

        if (!card) {
            card = document.createElement('div');
            card.className = 'vea-attribute-card';
            card.setAttribute('data-role', 'entity-card');
            card.setAttribute('data-source', 'magento');
            card.setAttribute('draggable', 'true');
            list.appendChild(card);
        }

        card.setAttribute('data-label', payload.label || '');
        card.setAttribute('data-code', payload.code || '');
        card.setAttribute('data-type', payload.type || '');
        card.setAttribute('data-scope', payload.scope || '');
        card.setAttribute('data-pending-create', payload.pending_create ? '1' : '0');
        card.setAttribute('data-create-label', payload.create_label || payload.label || '');
        card.setAttribute('data-search', mappingElements.searchText(payload));
        card.setAttribute('data-mapped', '0');
        card.setAttribute('role', 'group');
        card.setAttribute('tabindex', '0');
        card.setAttribute(
            'aria-label',
            $t('Dodaj atrybut do mapowania: %1').replace('%1', payload.label || payload.code || '')
        );
        card.hidden = true;
        card.innerHTML = attributeCardHtml(payload);

        initDrag(root);
        updateSidePanel(panel, true);

        return card;
    }

    function updateSidePanel(panel, shouldSort) {
        mappingBoard.updateSidePanel(panel, {
            sort: !!shouldSort
        });
    }

    function updateEmptySlotHint(slot) {
        var hint = slot.querySelector('[data-role="slot-hint"]');
        var opposite = getOppositeSlot(slot);
        var requiredType = getSlotType(opposite);
        var root = slot.closest('.vea-mapping');
        var attributeCode = opposite ? opposite.getAttribute('data-code') || '' : '';

        if (!hint || hasSlotAttribute(slot)) {
            return;
        }

        slot.innerHTML = emptySlotHtml(getSlotSide(slot), requiredType, root, attributeCode);
    }

    function setSlotEmpty(slot) {
        var side = getSlotSide(slot);
        var opposite = getOppositeSlot(slot);
        var optionEntry = slot.querySelector('[data-role="option-mapping-entry"]');

        slot.classList.add('is-empty');
        slot.classList.remove('has-option-mapping-action');
        slot.classList.remove('is-drop-ready', 'is-drop-rejected');
        slot.setAttribute('data-label', '');
        slot.setAttribute('data-code', '');
        slot.setAttribute('data-type', '');
        slot.setAttribute('data-scope', '');
        slot.setAttribute('data-pending-create', '0');
        slot.setAttribute('data-create-label', '');
        slot.removeAttribute('draggable');
        slot.innerHTML = emptySlotHtml(
            side,
            getSlotType(opposite),
            slot.closest('.vea-mapping'),
            opposite ? opposite.getAttribute('data-code') || '' : ''
        );
        if (optionEntry) {
            slot.appendChild(optionEntry);
        }
        updateEmptySlotHint(slot);
    }

    function setSlotFilled(slot, payload) {
        var optionEntry = slot.querySelector('[data-role="option-mapping-entry"]');

        slot.classList.remove('is-empty', 'is-drop-ready', 'is-drop-rejected');
        slot.setAttribute('data-label', payload.label || '');
        slot.setAttribute('data-code', payload.code || '');
        slot.setAttribute('data-type', payload.type || '');
        slot.setAttribute('data-scope', payload.scope || '');
        slot.setAttribute('data-pending-create', payload.pending_create ? '1' : '0');
        slot.setAttribute('data-create-label', payload.create_label || payload.label || '');
        slot.setAttribute('draggable', 'true');
        slot.innerHTML = [
            '<strong>',
            text.escapeHtml(payload.label),
            '</strong>',
            mappingElements.metaHtml(payload)
        ].join('');
        if (optionEntry) {
            slot.appendChild(optionEntry);
        }
    }

    function setRowStatus(row, rowStatus) {
        var statusTone = getStatusTone(rowStatus);
        var root = row.closest('.vea-mapping');

        row.classList.toggle('is-type-error', rowStatus === 'error');
        row.classList.toggle('vea-status-tone-ok', statusTone === 'ok');
        row.classList.toggle('vea-status-tone-warning', statusTone === 'warning');
        row.classList.toggle('vea-status-tone-error', statusTone === 'error');
        row.setAttribute('data-status-tone', statusTone);

        if (root) {
            updateMappingFilter(root);
        }
    }

    function updateMappingFilter(root) {
        mappingBoard.updateMappingFilter(root, true);
    }

    function updateOptionMappingAction(row) {
        var entry = row.querySelector('[data-role="option-mapping-entry"]');
        var button = entry ? entry.querySelector('[data-role="option-mapping-action"]') : null;
        var left = row.querySelector('[data-role="pair-slot"][data-side="ergo"]');
        var right = row.querySelector('[data-role="pair-slot"][data-side="magento"]');
        var available;

        if (!entry || !button) {
            return;
        }

        available = (left ? left.getAttribute('data-code') : '') === button.getAttribute('data-saved-ergo-code')
            && (right ? right.getAttribute('data-code') : '') === button.getAttribute('data-saved-magento-code');
        entry.hidden = !available;
        row.classList.toggle('has-option-mapping', available);
        if (right) {
            right.classList.toggle('has-option-mapping-action', available);
        }
    }

    function refreshRowAfterEdit(row) {
        var left = row.querySelector('[data-role="pair-slot"][data-side="ergo"]');
        var right = row.querySelector('[data-role="pair-slot"][data-side="magento"]');
        var leftFilled = hasSlotAttribute(left);
        var rightFilled = hasSlotAttribute(right);
        var leftType = getSlotType(left);
        var rightType = getSlotType(right);

        row.querySelectorAll('[data-role="pair-slot"]').forEach(updateEmptySlotHint);
        updateRowSearchData(row);
        updateOptionMappingAction(row);

        if (leftFilled && rightFilled && !canMapAttributeTypes(
            leftType,
            rightType,
            right.getAttribute('data-code') || ''
        )) {
            setRowStatus(row, 'error');
            showRowMessage(
                row,
                $t('Nie można połączyć: typ Ergonode "%1" nie pasuje do typu Magento "%2".')
                    .replace('%1', leftType)
                    .replace('%2', rightType)
            );
            return;
        }

        if (rightFilled && row.getAttribute('data-validation-message')
            && row.getAttribute('data-validation-code') === right.getAttribute('data-code')) {
            showRowMessage(row, row.getAttribute('data-validation-message'));
            setRowStatus(row, row.getAttribute('data-validation-tone') === 'error' ? 'error' : 'manual');
            return;
        }

        hideRowMessage(row);
        setRowStatus(row, leftFilled && rightFilled ? 'manual' : 'draft');
    }

    function getDragPayload(root, event) {
        return mappingBoard.getDragPayload(root, event, dragMimeType);
    }

    function getDropDecision(slot, payload) {
        var side = getSlotSide(slot);
        var opposite = getOppositeSlot(slot);
        var oppositeType = getSlotType(opposite);

        if (!payload || !payload.code) {
            return {
                allowed: false,
                message: $t('Nie rozpoznano przeciąganego atrybutu.')
            };
        }

        if (payload.origin === 'mapping') {
            return {
                allowed: false,
                message: $t('Upuść atrybut w bocznej strefie usuwania z mapowania.')
            };
        }

        if (payload.source !== side) {
            return {
                allowed: false,
                message: $t('Ten slot przyjmuje tylko atrybuty %1.')
                    .replace('%1', mappingElements.sourceLabel(side))
            };
        }

        if (!canDropOnSlot(slot, payload.type, payload.code)) {
            return {
                allowed: false,
                message: $t('Niezgodny typ: przeciągany atrybut ma typ "%1", a druga strona wymaga "%2".')
                    .replace('%1', payload.type)
                    .replace('%2', oppositeType)
            };
        }

        return {
            allowed: true,
            message: ''
        };
    }

    function clearMappingDropHints(root) {
        root.querySelectorAll('[data-role="mapping-row"]').forEach(function (row) {
            row.classList.remove('is-drop-row-compatible', 'is-drop-row-muted');
        });
        root.querySelectorAll('[data-role="pair-slot"]').forEach(function (slot) {
            slot.classList.remove(
                'is-drop-compatible',
                'is-drop-incompatible',
                'is-drop-occupied',
                'is-drop-wrong-side'
            );
        });
    }

    function getSlotHintState(slot, payload) {
        var oppositeType;

        if (!acceptsSourceDrop(payload)) {
            return '';
        }

        if (getSlotSide(slot) !== payload.source) {
            return 'wrong-side';
        }

        if (hasSlotAttribute(slot)) {
            return 'occupied';
        }

        oppositeType = getSlotType(getOppositeSlot(slot));

        if (!canDropOnSlot(slot, payload.type, payload.code)) {
            return 'incompatible';
        }

        return 'compatible';
    }

    function applyMappingDropHints(root, payload) {
        clearMappingDropHints(root);

        if (!acceptsSourceDrop(payload)) {
            return;
        }

        root.querySelectorAll('[data-role="mapping-row"]').forEach(function (row) {
            var hasCompatibleSlot = false;

            row.querySelectorAll('[data-role="pair-slot"]').forEach(function (slot) {
                var state = getSlotHintState(slot, payload);

                slot.classList.toggle('is-drop-compatible', state === 'compatible');
                slot.classList.toggle('is-drop-incompatible', state === 'incompatible');
                slot.classList.toggle('is-drop-occupied', state === 'occupied');
                slot.classList.toggle('is-drop-wrong-side', state === 'wrong-side');

                if (state === 'compatible') {
                    hasCompatibleSlot = true;
                }
            });

            row.classList.toggle('is-drop-row-compatible', hasCompatibleSlot);
            row.classList.toggle('is-drop-row-muted', !hasCompatibleSlot);
        });
    }

    function clearMappedDragState(root, keepDisablePanelState) {
        var state = dragDrop.state(root);

        root.classList.remove('is-dragging-from-mapping', 'is-dragging-ergo', 'is-dragging-magento');
        root.querySelectorAll('[data-role="pair-slot"].is-dragging').forEach(function (slot) {
            slot.classList.remove('is-dragging');
        });
        if (!keepDisablePanelState) {
            clearDisablePanelStates(root);
        }
        state.payload = null;
        state.mappedElement = null;
    }

    function setSideDragState(root, source, active, payload) {
        root.classList.toggle('is-dragging-to-mapping', active);
        root.classList.toggle('is-dragging-source-ergo', active && source === 'ergo');
        root.classList.toggle('is-dragging-source-magento', active && source === 'magento');

        if (active) {
            applyMappingDropHints(root, payload || dragDrop.state(root).payload);
        } else {
            clearMappingDropHints(root);
        }
    }

    function clearSideDragState(root, keepCreatePanelState) {
        var state = dragDrop.state(root);

        root.classList.remove('is-dragging-to-mapping', 'is-dragging-source-ergo', 'is-dragging-source-magento');
        root.querySelectorAll('[data-role="entity-card"].is-dragging').forEach(function (card) {
            card.classList.remove('is-dragging');
        });
        clearMappingDropHints(root);
        if (!keepCreatePanelState) {
            clearCreatePanelStates(root);
        }
        state.payload = null;
        state.sourceElement = null;
    }

    function createEmptyMappingRow() {
        var row = document.createElement('article');

        row.className = 'vea-pair-row vea-attribute-pair-row vea-status-tone-warning';
        row.setAttribute('data-role', 'mapping-row');
        row.setAttribute('data-status-tone', 'warning');
        row.setAttribute('data-search', '');
        row.innerHTML = [
            '<div class="vea-pair-card is-empty" data-role="pair-slot" data-side="ergo" data-label="" data-code="" data-type="" data-scope="">',
            emptySlotHtml('ergo', ''),
            '</div>',
            unlinkMappingHtml(),
            '<div class="vea-pair-card is-empty" data-role="pair-slot" data-side="magento" data-label="" data-code="" data-type="" data-scope="">',
            emptySlotHtml('magento', ''),
            '</div>',
            '<div class="vea-pair-message" data-role="pair-message" hidden></div>'
        ].join('');
        return row;
    }

    function createMappingRowFromPayload(root, payload) {
        var list = root.querySelector('[data-role="mapping-list"]');
        var row;
        var slot;

        if (!list || !acceptsSourceDrop(payload)) {
            return null;
        }

        if (isPayloadAlreadyMapped(root, payload)) {
            setAttributeMapped(root, payload.source, payload.code, true);
            return null;
        }

        row = createEmptyMappingRow();
        list.insertBefore(row, list.firstChild);
        slot = row.querySelector('[data-role="pair-slot"][data-side="' + payload.source + '"]');

        if (slot) {
            setSlotFilled(slot, payload);
            setAttributeMapped(root, payload.source, payload.code, true);
            refreshRowAfterEdit(row);
            mappingElements.markNewMapping(row, payload.source);
        }

        initPairSlots(root);
        initUnlinkButtons(root);
        updateMappingFilter(root);

        return row;
    }

    function fillComplementaryMappingRow(root, payload) {
        var row = mappingBoard.findComplementaryRow(root, payload.source, function (slot) {
            return getDropDecision(slot, payload).allowed;
        });
        var slot;

        if (!row || isPayloadAlreadyMapped(root, payload)) {
            return null;
        }

        slot = row.querySelector('[data-role="pair-slot"][data-side="' + payload.source + '"]');
        setSlotFilled(slot, payload);
        setAttributeMapped(root, payload.source, payload.code, true);
        refreshRowAfterEdit(row);
        mappingElements.markNewMapping(row, payload.source);
        updateMappingFilter(root);

        return row;
    }

    function addSourcePayloadToMapping(root, payload, pairComplementary) {
        var panel = root.querySelector('[data-role="mapping-panel"]');
        var row;

        if (!panel || !acceptsSourceDrop(payload)) {
            return null;
        }

        setCreatePanelActive(panel);
        row = pairComplementary === false ? null : fillComplementaryMappingRow(root, payload);
        row = row || createMappingRowFromPayload(root, payload);

        window.setTimeout(function () {
            panel.classList.remove('is-create-drop-active');
            clearCreatePanelStates(root);
        }, 650);

        return row;
    }

    function createMatchedMappingRow(root, leftPayload, rightPayload) {
        var list = root.querySelector('[data-role="mapping-list"]');
        var row;
        var leftSlot;
        var rightSlot;

        if (!list || !leftPayload || !rightPayload) {
            return null;
        }

        if (isPayloadAlreadyMapped(root, leftPayload) || isPayloadAlreadyMapped(root, rightPayload)) {
            return null;
        }

        row = createEmptyMappingRow();
        list.insertBefore(row, list.firstChild);
        leftSlot = row.querySelector('[data-role="pair-slot"][data-side="ergo"]');
        rightSlot = row.querySelector('[data-role="pair-slot"][data-side="magento"]');

        if (leftSlot && rightSlot) {
            setSlotFilled(leftSlot, leftPayload);
            setSlotFilled(rightSlot, rightPayload);
            setAttributeMapped(root, leftPayload.source, leftPayload.code, true);
            setAttributeMapped(root, rightPayload.source, rightPayload.code, true);
            refreshRowAfterEdit(row);
            mappingElements.markNewMapping(row, 'magento');
        }

        return row;
    }

    function applyAutoMatches(root, matches) {
        var matched = 0;

        (Array.isArray(matches) ? matches : []).forEach(function (match) {
            var leftPayload = match && match.left;
            var rightPayload = match && match.right;
            var leftSlot;
            var rightSlot;
            var targetSlot;
            var row;
            var missingSide;

            if (!leftPayload || !rightPayload) {
                return;
            }

            leftSlot = findMappedSlot(root, 'ergo', leftPayload.code);
            rightSlot = findMappedSlot(root, 'magento', rightPayload.code);
            if (leftSlot && rightSlot) {
                return;
            }

            if (leftSlot || rightSlot) {
                row = (leftSlot || rightSlot).closest('[data-role="mapping-row"]');
                missingSide = leftSlot ? 'magento' : 'ergo';
                targetSlot = row ? row.querySelector(
                    '[data-role="pair-slot"][data-side="' + missingSide + '"]'
                ) : null;
                if (!targetSlot || hasSlotAttribute(targetSlot)) {
                    return;
                }

                setSlotFilled(targetSlot, leftSlot ? rightPayload : leftPayload);
                setAttributeMapped(
                    root,
                    missingSide,
                    leftSlot ? rightPayload.code : leftPayload.code,
                    true
                );
                refreshRowAfterEdit(row);
                mappingElements.markNewMapping(row, missingSide);
                matched++;
                return;
            }

            if (createMatchedMappingRow(root, leftPayload, rightPayload)) {
                matched++;
            }
        });

        if (matched) {
            initPairSlots(root);
            initUnlinkButtons(root);
            updateMappingFilter(root);
            scheduleSaveStateUpdate(root);
        }

        return matched;
    }

    function bindPairSlot(root, slot) {
        if (!root.veaWorkspace.claim(slot, 'pair-slot')) {
            return;
        }

        updateEmptySlotHint(slot);

        root.veaWorkspace.listen(slot, 'dragstart', function (event) {
            var payload = mappingElements.slotPayload(slot);

            if (mappingElements.isActionTarget(event.target)) {
                event.preventDefault();
                return;
            }

            if (!payload) {
                event.preventDefault();
                return;
            }

            payload.origin = 'mapping';
            dragDrop.state(root).payload = payload;
            dragDrop.state(root).mappedElement = slot;
            slot.classList.add('is-dragging');
            setMappedDragState(root, payload.source, true);
            event.dataTransfer.effectAllowed = 'move';
            dragDrop.write(event.dataTransfer, payload, dragMimeType);
        });

        root.veaWorkspace.listen(slot, 'dragend', function () {
            clearMappedDragState(root);
        });

        root.veaWorkspace.listen(slot, 'dragover', function (event) {
            var payload = getDragPayload(root, event);
            var decision = getDropDecision(slot, payload);

            if (acceptsSourceDrop(payload) && hasSlotAttribute(slot)) {
                clearSlotDropState(slot);
                return;
            }

            if (!decision.allowed && acceptsSourceDrop(payload) && payload.source !== getSlotSide(slot)) {
                clearSlotDropState(slot);
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            slot.classList.toggle('is-drop-ready', decision.allowed);
            slot.classList.toggle('is-drop-rejected', !decision.allowed);
            event.dataTransfer.dropEffect = decision.allowed ? 'move' : 'none';
        });

        root.veaWorkspace.listen(slot, 'dragleave', function () {
            clearSlotDropState(slot);
        });

        root.veaWorkspace.listen(slot, 'drop', function (event) {
            var row = slot.closest('[data-role="mapping-row"]');
            var payload = getDragPayload(root, event);
            var decision = getDropDecision(slot, payload);
            var previousPayload = mappingElements.slotPayload(slot);

            if (acceptsSourceDrop(payload) && hasSlotAttribute(slot)) {
                clearSlotDropState(slot);
                return;
            }

            if (!decision.allowed && acceptsSourceDrop(payload) && payload.source !== getSlotSide(slot)) {
                clearSlotDropState(slot);
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            clearSlotDropState(slot);

            if (!row) {
                return;
            }

            if (!decision.allowed) {
                row.classList.add('is-type-error');
                showRowMessage(row, decision.message);
                window.setTimeout(function () {
                    row.classList.remove('is-type-error');
                }, 1800);
                return;
            }

            if (isPayloadAlreadyMapped(root, payload, slot)) {
                row.classList.add('is-type-error');
                showRowMessage(row, $t('Ten atrybut jest już użyty w innym mapowaniu.'));
                setAttributeMapped(root, payload.source, payload.code, true);
                window.setTimeout(function () {
                    row.classList.remove('is-type-error');
                }, 1800);
                return;
            }

            if (previousPayload) {
                setAttributeMapped(root, previousPayload.source, previousPayload.code, false);
            }

            setSlotFilled(slot, payload);
            setAttributeMapped(root, payload.source, payload.code, true);
            row.classList.remove('is-type-error');
            refreshRowAfterEdit(row);
        });
    }

    function initPairSlots(root) {
        root.querySelectorAll('[data-role="pair-slot"]').forEach(function (slot) {
            bindPairSlot(root, slot);
        });
    }

    function initUnlinkButtons(root) {
        root.veaWorkspace.delegate('click', '[data-role="unlink-mapping"]', function (event, button) {
            var row;

            event.preventDefault();
            event.stopPropagation();
            row = button.closest('[data-role="mapping-row"]');
            unlinkMappingRow(root, row);
        });
    }

    function initCreateMagentoAttribute(root) {
        root.veaWorkspace.delegate('click', '[data-role="create-magento-attribute"]', function (event, button) {
            var slot;
            var row;
            var leftSlot;
            var leftPayload;
            var attribute;
            var existingCard;

            event.preventDefault();
            event.stopPropagation();

            if (button.disabled) {
                return;
            }

            slot = button.closest('[data-role="pair-slot"][data-side="magento"]');
            row = slot ? slot.closest('[data-role="mapping-row"]') : null;
            leftSlot = row ? row.querySelector('[data-role="pair-slot"][data-side="ergo"]') : null;
            leftPayload = mappingElements.slotPayload(leftSlot);

            if (!slot || hasSlotAttribute(slot) || !leftPayload) {
                return;
            }

            if (!canCreateMagentoAttribute(root, leftPayload.type)) {
                root.veaContext.message.show('error', $t('Tego typu atrybutu nie można utworzyć automatycznie w Magento.'));
                return;
            }

            attribute = buildPendingMagentoAttribute(leftPayload);
            existingCard = findAttributeCard(root, 'magento', attribute.code);
            if (existingCard && existingCard.getAttribute('data-pending-create') !== '1') {
                attribute = mappingElements.cardPayload(existingCard);
            }

            if (!attribute.code || !attribute.type) {
                root.veaContext.message.show('error', $t('Brakuje kodu albo typu atrybutu Ergonode.'));
                return;
            }

            if (isPayloadAlreadyMapped(root, attribute, slot)) {
                root.veaContext.message.show('error', $t('Ten atrybut Magento jest już użyty w innym mapowaniu.'));
                setAttributeMapped(root, attribute.source, attribute.code, true);
                return;
            }

            if (attribute.pending_create) {
                appendMagentoAttributeCard(root, attribute);
            }
            setSlotFilled(slot, attribute);
            setAttributeMapped(root, 'magento', attribute.code, true);

            if (row) {
                refreshRowAfterEdit(row);
            }
            updateMappingFilter(root);
            root.veaContext.message.show(
                'success',
                attribute.pending_create
                    ? 'Atrybut zostanie utworzony w Magento po zapisaniu mapowania.'
                    : $t('Atrybut Magento już istnieje i został podpięty do mapowania.'),
                3200
            );
        });
    }

    function normalizeRowAfterSlotRemoval(root, row) {
        if (!row) {
            return;
        }

        if (isRowEmpty(row)) {
            row.remove();
            updateMappingFilter(root);
            return;
        }

        refreshRowAfterEdit(row);
    }

    function removeMappedSlot(root, slot, payload) {
        var row = slot ? slot.closest('[data-role="mapping-row"]') : null;

        if (!slot || !payload) {
            return;
        }

        setAttributeMapped(root, payload.source, payload.code, false);
        setSlotEmpty(slot);
        normalizeRowAfterSlotRemoval(root, row);
    }

    function unlinkMappingRow(root, row) {
        if (!row) {
            return false;
        }

        row.querySelectorAll('[data-role="pair-slot"]').forEach(function (slot) {
            var payload = mappingElements.slotPayload(slot);

            if (payload) {
                setAttributeMapped(root, payload.source, payload.code, false);
            }
        });
        row.remove();
        updateMappingFilter(root);

        return true;
    }

    function initDrag(root) {
        root.querySelectorAll('[data-role="entity-card"]').forEach(function (card) {
            if (!root.veaWorkspace.claim(card, 'entity-card-drag')) {
                return;
            }

            root.veaWorkspace.listen(card, 'dragstart', function (event) {
                var payload = getSourceCardPayload(card);

                if (!payload) {
                    event.preventDefault();
                    return;
                }

                dragDrop.state(root).payload = payload;
                dragDrop.state(root).sourceElement = card;
                card.classList.add('is-dragging');
                setSideDragState(root, payload.source, true, payload);
                event.dataTransfer.effectAllowed = 'move';
                dragDrop.write(event.dataTransfer, payload, dragMimeType);
            });

            root.veaWorkspace.listen(card, 'dragend', function () {
                clearSideDragState(root);
            });

            root.veaWorkspace.listen(card, 'dblclick', function (event) {
                var payload;

                if (mappingElements.isActionTarget(event.target)) {
                    return;
                }

                payload = getSourceCardPayload(card);
                if (!payload) {
                    return;
                }

                event.preventDefault();
                addSourcePayloadToMapping(root, payload);
            });
        });

        root.querySelectorAll('[data-role="mapping-panel"]').forEach(function (panel) {
            root.veaWorkspace.listen(panel, 'dragover', function (event) {
                var payload = getDragPayload(root, event);

                if (!acceptsSourceDrop(payload)) {
                    return;
                }

                event.preventDefault();
                setCreatePanelReady(panel, true);
                event.dataTransfer.dropEffect = 'move';
            });

            root.veaWorkspace.listen(panel, 'dragleave', function (event) {
                if (!event.relatedTarget || !panel.contains(event.relatedTarget)) {
                    setCreatePanelReady(panel, false);
                }
            });

            root.veaWorkspace.listen(panel, 'drop', function (event) {
                var payload = getDragPayload(root, event);

                if (!acceptsSourceDrop(payload)) {
                    return;
                }

                event.preventDefault();
                setCreatePanelActive(panel);
                createMappingRowFromPayload(root, payload);
                clearSideDragState(root, true);

                window.setTimeout(function () {
                    panel.classList.remove('is-create-drop-active');
                    clearCreatePanelStates(root);
                }, 650);
            });
        });

        root.querySelectorAll('[data-role="attribute-side"]').forEach(function (panel) {
            root.veaWorkspace.listen(panel, 'dragover', function (event) {
                var payload = getDragPayload(root, event);

                if (!acceptsMappedDrop(panel, payload)) {
                    return;
                }

                event.preventDefault();
                setDisablePanelReady(panel, true);
                event.dataTransfer.dropEffect = 'move';
            });

            root.veaWorkspace.listen(panel, 'dragleave', function (event) {
                if (!event.relatedTarget || !panel.contains(event.relatedTarget)) {
                    setDisablePanelReady(panel, false);
                }
            });

            root.veaWorkspace.listen(panel, 'drop', function (event) {
                var payload = getDragPayload(root, event);

                if (!acceptsMappedDrop(panel, payload)) {
                    return;
                }

                event.preventDefault();
                setDisablePanelActive(panel);
                removeMappedSlot(root, dragDrop.state(root).mappedElement, payload);
                clearMappedDragState(root, true);

                window.setTimeout(function () {
                    panel.classList.remove('is-disable-drop-active');
                    clearDisablePanelStates(root);
                }, 650);
            });
        });
    }

    function initRefreshErgonode(root) {
        var button = root.querySelector('[data-role="refresh-ergonode"]');
        var panel = button ? button.closest('[data-role="attribute-side"]') : null;

        if (!button || !panel) {
            return;
        }

        root.veaWorkspace.listen(button, 'click', function () {
            var config = root.veaConfig || {};
            var summary = refreshResult.empty();

            if (button.disabled) {
                return;
            }

            panel.classList.add('is-refreshing');
            buttons.setBusy(button, true);
            request.paginate({
                url: config.urls ? config.urls.refresh : '',
                config: config,
                pageSize: 200,
                data: function () {
                    return {};
                },
                onPage: function (response) {
                    refreshResult.merge(summary, response);
                }
            }).then(function () {
                refreshResult.persist(summary);
                window.location.reload();
            }).catch(function (error) {
                root.veaContext.message.error(
                    $t('Nie udało się odświeżyć atrybutów'),
                    error,
                    $t('Nie udało się wczytać atrybutów z Ergonode.'),
                    languageMappingAction(config, error)
                );
                panel.classList.remove('is-refreshing');
                buttons.setBusy(button, false);
            });
        });
    }

    function initSynchronizeErgonode(root) {
        var actionButtons = root.querySelectorAll('[data-synchronization-action]');

        if (actionButtons.length === 0) {
            return;
        }

        actionButtons.forEach(function (button) {
            root.veaWorkspace.listen(button, 'click', function () {
                var config = root.veaConfig || {};
                var action = button.getAttribute('data-synchronization-action') || 'sync';
                var resetOnly = action === 'reset-cursor';

                if (button.disabled) {
                    return;
                }
                if (action !== 'sync' && !window.confirm($t(
                    action === 'reset-cursor-and-sync'
                        ? 'Reset the attributeStream cursor and synchronize attributes from the beginning?'
                        : 'Reset the attributeStream cursor?'
                ))) {
                    return;
                }
                if (!resetOnly && hasUnsavedChanges(root)) {
                    root.veaContext.message.show(
                        'error',
                        $t('Save manual changes before synchronizing attributes.')
                    );
                    return;
                }

                buttons.setBusy(button, true, {busyClass: 'is-working'});
                request.post(config.urls ? config.urls.sync : '', config, {
                    synchronization_action: action
                }).then(function (response) {
                    root.veaContext.message.show(
                        'success',
                        response.message || $t('Attributes have been synchronized.')
                    );
                    if (resetOnly) {
                        buttons.setBusy(button, false, {busyClass: 'is-working'});
                        return;
                    }
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 350);
                }).catch(function (error) {
                    root.veaContext.message.error(
                        $t('Unable to synchronize attributes'),
                        error,
                        $t('Unable to synchronize attributes with Magento.')
                    );
                    buttons.setBusy(button, false, {busyClass: 'is-working'});
                });
            });
        });
    }

    function setAutoMatchAvailability(root, matches, pending) {
        var button = root ? root.querySelector('[data-role="auto-match"]') : null;
        var availableCount = Array.isArray(matches) ? matches.length : 0;
        var busy = button && button.getAttribute('aria-busy') === 'true';

        if (!button) {
            return availableCount;
        }

        button.setAttribute('data-available-count', String(availableCount));
        button.disabled = Boolean(pending) || busy || availableCount === 0;

        return availableCount;
    }

    function refreshAutoMatchAvailability(root, requestId) {
        var config = root.veaConfig || {};

        return request.post(config.urls ? config.urls.auto_match : '', config, {
            payload: JSON.stringify(collectSavePayload(root))
        }).then(function (response) {
            if (root.veaAutoMatchAvailabilityRequestId === requestId) {
                setAutoMatchAvailability(root, response.matches, false);
            }

            return response;
        }).catch(function () {
            if (root.veaAutoMatchAvailabilityRequestId === requestId) {
                setAutoMatchAvailability(root, [], false);
            }

            return null;
        });
    }

    function scheduleAutoMatchAvailability(root) {
        var requestId = (root.veaAutoMatchAvailabilityRequestId || 0) + 1;

        root.veaAutoMatchAvailabilityRequestId = requestId;
        window.clearTimeout(root.veaAutoMatchAvailabilityTimer);
        setAutoMatchAvailability(root, [], true);
        root.veaAutoMatchAvailabilityTimer = window.setTimeout(function () {
            refreshAutoMatchAvailability(root, requestId);
        }, 100);
    }

    function initAutoMatch(root) {
        var button = root.querySelector('[data-role="auto-match"]');
        var label = button ? button.querySelector('[data-role="auto-match-label"]') : null;
        var defaultText = label ? label.textContent : '';
        var observer;
        var mappingList;

        if (!button) {
            return;
        }

        root.veaWorkspace.listen(button, 'click', function () {
            var config = root.veaConfig || {};

            if (button.disabled) {
                return;
            }

            buttons.setBusy(button, true, {busyClass: 'is-working'});
            request.post(config.urls ? config.urls.auto_match : '', config, {
                payload: JSON.stringify(collectSavePayload(root))
            }).then(function (response) {
                var matched = applyAutoMatches(root, response.matches);
                var conflicts = Array.isArray(response.conflicts) ? response.conflicts.length : 0;

                if (label) {
                    label.textContent = matched
                        ? $t('Dopasowano %1').replace('%1', matched)
                        : (conflicts ? $t('Niezgodne typy: %1').replace('%1', conflicts) : $t('Brak dopasowań'));
                }

                window.setTimeout(function () {
                    if (label) {
                        label.textContent = defaultText;
                    }
                }, 1200);
            }).catch(function (error) {
                root.veaContext.message.error(
                    $t('Nie udało się automatycznie dopasować atrybutów'),
                    error,
                    $t('Nie udało się przygotować propozycji mapowania.')
                );
            }).finally(function () {
                setAutoMatchAvailability(root, [], true);
                buttons.setBusy(button, false, {
                    busyClass: 'is-working',
                    disabledWhenIdle: function () {
                        return true;
                    }
                });
                scheduleAutoMatchAvailability(root);
                updateSaveState(root);
            });
        });

        if (typeof MutationObserver !== 'undefined') {
            observer = new MutationObserver(function () {
                scheduleAutoMatchAvailability(root);
            });
            observer.observe(root, {
                attributeFilter: ['aria-pressed', 'data-code', 'data-mapped', 'data-pending-create'],
                attributes: true,
                subtree: true
            });
            mappingList = root.querySelector('[data-role="mapping-list"]');
            if (mappingList) {
                observer.observe(mappingList, {
                    childList: true,
                    subtree: true
                });
            }
            root.veaWorkspace.cleanup(function () {
                observer.disconnect();
            });
        }
        root.veaWorkspace.cleanup(function () {
            window.clearTimeout(root.veaAutoMatchAvailabilityTimer);
            root.veaAutoMatchAvailabilityRequestId = (root.veaAutoMatchAvailabilityRequestId || 0) + 1;
        });
        scheduleAutoMatchAvailability(root);
    }

    function initOptionMappingActions(root) {
        root.querySelectorAll('[data-role="option-mapping-action"]').forEach(function (button) {
            if (!root.veaWorkspace.claim(button, 'option-mapping-action')) {
                return;
            }

            root.veaWorkspace.listen(button, 'click', function () {
                var url = button.getAttribute('data-url') || '';
                var modalUrl = button.getAttribute('data-modal-url') || '';
                var navigate;

                if (!url) {
                    return;
                }

                if (modalUrl) {
                    optionNavigation.request({
                        dirty: hasUnsavedChanges(root),
                        navigate: function () {
                            optionMappingModal.open(root.veaWorkspace, root, modalUrl);
                        },
                        saveAndNavigate: function () {
                            saveMappings(root).then(function () {
                                optionMappingModal.open(root.veaWorkspace, root, modalUrl);
                            }).catch(function () {});
                        }
                    });

                    return;
                }

                navigate = function () {
                    window.location.assign(url);
                };
                optionNavigation.request({
                    dirty: hasUnsavedChanges(root),
                    navigate: navigate,
                    saveAndNavigate: function () {
                        saveMappings(root).then(navigate).catch(function () {});
                    }
                });
            });
        });
    }

    function applyValidationMessages(root, messages) {
        root.querySelectorAll('[data-role="mapping-row"]').forEach(function (row) {
            var right = row.querySelector('[data-role="pair-slot"][data-side="magento"]');
            var code = right ? right.getAttribute('data-code') : '';
            var validation = messages[code];

            if (!validation) {
                return;
            }
            row.setAttribute('data-validation-code', code);
            row.setAttribute('data-validation-message', validation.message || '');
            row.setAttribute('data-validation-tone', validation.tone || '');
            refreshRowAfterEdit(row);
        });
    }

    function saveMappings(root) {
        var button = root.querySelector('[data-role="save-mapping"]');
        var label = button ? button.querySelector('[data-role="save-mapping-label"]') : null;
        var defaultText = label ? label.veaDefaultText || label.textContent : '';
        var config = root.veaConfig || {};
        var payload;

        if (!button) {
            return Promise.reject(new Error($t('Brak przycisku zapisu mapowania.')));
        }

        if (button.disabled) {
            return Promise.reject(new Error($t('Zapisywanie mapowania już trwa.')));
        }

        if (label) {
            label.veaDefaultText = defaultText;
        }
        buttons.setBusy(button, true, {busyClass: 'is-saved'});
        payload = collectSavePayload(root);

        if (label) {
            label.textContent = $t('Zapisywanie');
        }

        return request.post(config.urls ? config.urls.save : '', config, {
            payload: JSON.stringify(payload)
        }).then(function (response) {
            mappingSaveResult.persist('attribute', response);
            applyValidationMessages(root, response.validation || {});
            root.veaContext.dirty.capture();
            updateSaveState(root);
            if (label) {
                label.textContent = $t('Zapisano');
            }

            return response;
        }).catch(function (error) {
            root.veaContext.message.error(
                $t('Nie udało się zapisać mapowania'),
                error,
                $t('Nie udało się zapisać mapowań.')
            );
            buttons.setBusy(button, false, {busyClass: 'is-saved'});
            updateSaveState(root);
            if (label) {
                label.textContent = defaultText;
            }
            throw error;
        });
    }

    function initSaveAction(root) {
        var button = root.querySelector('[data-role="save-mapping"]');

        if (!button) {
            return;
        }

        root.veaWorkspace.listen(button, 'click', function () {
            saveMappings(root).then(function () {
                window.setTimeout(function () {
                    window.location.reload();
                }, 500);
            }).catch(function () {});
        });
    }

    function collectSavePayload(root) {
        return {
            mappings: mappingBoard.collectMappings(root, true),
            visibility: mappingBoard.collectVisibility(root)
        };
    }

    function hasUnsavedChanges(root) {
        return root.veaContext && root.veaContext.dirty
            ? root.veaContext.dirty.isDirty()
            : false;
    }

    function updateSaveState(root) {
        var button = root.querySelector('[data-role="save-mapping"]');
        var dirty = hasUnsavedChanges(root);
        var busy = button && button.getAttribute('aria-busy') === 'true';

        if (button && !busy) {
            button.disabled = !dirty;
        }
    }

    function scheduleSaveStateUpdate(root) {
        window.clearTimeout(root.veaSaveStateTimer);
        root.veaSaveStateTimer = window.setTimeout(function () {
            updateSaveState(root);
        }, 0);
    }

    function initSaveState(root) {
        var observer;

        root.veaContext.dirty.capture();
        if (typeof MutationObserver !== 'undefined') {
            observer = new MutationObserver(function () {
                scheduleSaveStateUpdate(root);
            });
            observer.observe(root, {
                attributeFilter: [
                    'data-code',
                    'data-create-label',
                    'data-label',
                    'data-pending-create',
                    'data-scope',
                    'data-type'
                ],
                attributes: true,
                childList: true,
                subtree: true
            });
            root.veaWorkspace.cleanup(function () {
                observer.disconnect();
            });
        }

        root.veaWorkspace.cleanup(function () {
            window.clearTimeout(root.veaSaveStateTimer);
        });

        updateSaveState(root);
    }

    function updateAllSidePanels(root) {
        root.querySelectorAll('[data-role="attribute-side"]').forEach(function (panel) {
            updateSidePanel(panel);
        });
    }

    function initAttributeMapping(config, element) {
        configureAttributeTypeCompatibility(config && config.attribute_type_compatibility);
        configureMagentoAttributeTypeConstraints(config && config.magento_attribute_type_constraints);

        return workspace.mount(element, function (scope) {
            var requirements = mappingRequirements.create(element);
            var requirementObserver;

            element.veaWorkspace = scope;
            element.veaConfig = config || {};
            synchronizationActions.bind(scope, element);
            element.veaMappingRequirements = requirements;
            workspaceContext.create(scope, element, {
                serialize: function () {
                    return collectSavePayload(element);
                }
            });
            element.veaUnsavedNavigation = unsavedNavigation.bind(scope, {
                isDirty: function () {
                    return hasUnsavedChanges(element);
                },
                navigate: function (url) {
                    window.location.assign(url);
                },
                save: function () {
                    return saveMappings(element);
                }
            });
            refreshResult.show(element.veaContext.message, refreshResult.consume());
            mappingSaveResult.show(
                element.veaContext.message,
                mappingSaveResult.consume('attribute')
            );
            setupErgonodeEntityOptions(element);
            snapshotRemoval.bind(scope, element, config, {
                isDirty: function () {
                    return hasUnsavedChanges(element);
                },
                message: element.veaContext.message
            });
            scope.cleanup(function () {
                dragDrop.clear(element);
                delete element.veaMappingRequirements;
                delete element.veaUnsavedNavigation;
                delete element.veaWorkspace;
            });

            requirements.refresh();
            sourceBulkTransfer.bind(scope, element, {
                transfer: function (card) {
                    var payload = getSourceCardPayload(card);

                    return payload ? !!addSourcePayloadToMapping(element, payload) : false;
                }
            });
            if (typeof MutationObserver !== 'undefined') {
                requirementObserver = new MutationObserver(function () {
                    requirements.refresh();
                });
                requirementObserver.observe(element, {
                    attributeFilter: ['data-code'],
                    attributes: true,
                    childList: true,
                    subtree: true
                });
                scope.cleanup(function () {
                    requirementObserver.disconnect();
                });
            }
            element.querySelectorAll('[data-role="attribute-side"]').forEach(function (panel) {
                mappingBoard.setupSidePanel(panel);
                updateSidePanel(panel);
                search.bindSort(scope, panel, {
                    toggleSelector: '[data-role="attribute-sort-toggle"]',
                    directionSelector: '[data-role="attribute-sort-direction"]',
                    directionLabelSelector: '[data-role="attribute-sort-direction-label"]',
                    labelSelector: '[data-role="attribute-sort-label"]',
                    defaultValue: 'label',
                    values: ['label', 'code'],
                    options: {
                        label: {label: $t('Nazwa'), ariaLabel: $t('Sortuj po nazwie')},
                        code: {label: $t('Kod'), ariaLabel: $t('Sortuj po kodzie')}
                    },
                    ascLabel: $t('Sortuj rosnąco'),
                    ascOptionLabel: $t('Góra'),
                    descLabel: $t('Sortuj malejąco'),
                    descOptionLabel: $t('Dół'),
                    update: function (currentPanel) {
                        updateSidePanel(currentPanel, true);
                    }
                });
            });

            search.bind(scope, {
                inputSelector: '[data-role="attribute-search"]',
                events: ['input', 'change'],
                update: function (query, input) {
                    updateSidePanel(input.closest('[data-role="attribute-side"]'));
                }
            });
            scope.delegate('click', '[data-role="attribute-active-toggle"]', function (event, button) {
                var card = button.closest('[data-role="entity-card"]');

                buttons.togglePressed(button);
                updateSidePanel(button.closest('[data-role="attribute-side"]'));
                snapshotRemoval.setActiveState(card, buttons.isPressed(button));
                scheduleSaveStateUpdate(element);
            });
            scope.delegate('click', '[data-role="entity-add-to-mapping"]', function (event, button) {
                var card = button.closest('[data-role="entity-card"]');
                var payload = getSourceCardPayload(card);

                event.preventDefault();
                event.stopPropagation();
                if (payload) {
                    addSourcePayloadToMapping(element, payload);
                }
            });
            scope.delegate('click', '[data-role="entity-create-magento-attribute"]', function (event, button) {
                var card = button.closest('[data-role="entity-card"]');
                var payload = getSourceCardPayload(card);
                var row;
                var createButton;

                event.preventDefault();
                event.stopPropagation();
                if (!payload || !canCreateMagentoAttribute(element, payload.type)) {
                    return;
                }

                row = addSourcePayloadToMapping(element, payload, false);
                createButton = row ? row.querySelector('[data-role="create-magento-attribute"]') : null;
                if (createButton) {
                    createButton.click();
                }
            });
            scope.delegate('keydown', '[data-role="entity-card"]', function (event, card) {
                if ((event.key !== 'Enter' && event.key !== ' ') || mappingElements.isActionTarget(event.target)) {
                    return;
                }

                event.preventDefault();
                card.dispatchEvent(new MouseEvent('dblclick', {bubbles: true}));
            });
            scope.delegate('click', '[data-role="visibility-toggle"]', function (event, button) {
                var panel = button.closest('[data-role="attribute-side"]');

                mappingBoard.toggleVisibility(button);
                if (panel) {
                    updateSidePanel(panel);
                } else {
                    updateAllSidePanels(element);
                }
            });
            search.bind(scope, {
                inputSelector: '[data-role="mapping-search"]',
                events: ['input', 'change'],
                update: function () {
                    updateMappingFilter(element);
                }
            });
            mappingBoard.initializeVisibility(element);
            updateMappingFilter(element);
            initDrag(element);
            initPairSlots(element);
            initUnlinkButtons(element);
            initCreateMagentoAttribute(element);
            completeMissing(element);
            initRefreshErgonode(element);
            initSynchronizeErgonode(element);
            initAutoMatch(element);
            initSaveState(element);
            initOptionMappingActions(element);
            initSaveAction(element);
        });
    }

    initAttributeMapping.canMapAttributeTypes = canMapAttributeTypes;
    initAttributeMapping.compatibleAttributeTypes = compatibleAttributeTypes;
    initAttributeMapping.configureAttributeTypeCompatibility = configureAttributeTypeCompatibility;
    initAttributeMapping.configureMagentoAttributeTypeConstraints = configureMagentoAttributeTypeConstraints;
    initAttributeMapping.applyAutoMatches = applyAutoMatches;
    initAttributeMapping.setAutoMatchAvailability = setAutoMatchAvailability;
    initAttributeMapping.canCreateMagentoAttribute = canCreateMagentoAttribute;
    initAttributeMapping.createMagentoAttributeAction = createMagentoAttributeAction;
    initAttributeMapping.hasExistingMagentoAttribute = hasExistingMagentoAttribute;
    initAttributeMapping.unlinkMappingRow = unlinkMappingRow;

    return initAttributeMapping;
});
