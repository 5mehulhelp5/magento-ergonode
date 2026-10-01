define([
    'Ergonode_CoreAdminUi/js/mapping-save-result',
    'Ergonode_CoreAdminUi/js/mapping-board',
    'Ergonode_CoreAdminUi/js/complete-missing',
    'Ergonode_CoreAdminUi/js/workspace',
    'Ergonode_CoreAdminUi/js/text',
    'Ergonode_CoreAdminUi/js/mapping-elements',
    'Ergonode_CoreAdminUi/js/workspace-context',
    'Ergonode_CoreAdminUi/js/unsaved-navigation',
    'Ergonode_CoreAdminUi/js/buttons',
    'Ergonode_CoreAdminUi/js/request',
    'Ergonode_CoreAdminUi/js/search',
    'Ergonode_CoreAdminUi/js/drag-drop',
    'Ergonode_CoreAdminUi/js/source-bulk-transfer',
    'Ergonode_CoreAdminUi/js/snapshot-removal',
    'mage/translate'
], function (mappingSaveResult, mappingBoard, completeMissing, workspace, text, mappingElements, workspaceContext, unsavedNavigation, buttons, request, search, dragDrop, sourceBulkTransfer, snapshotRemoval, $t) {
    'use strict';

    var dragMimeType = 'application/vnd.ergonode.option+json';
    var getSlotSide = mappingBoard.getSlotSide;
    var getOppositeSlot = mappingBoard.getOppositeSlot;
    var getSlotType = mappingBoard.getSlotType;
    var hasSlotOption = mappingBoard.hasSlotValue;
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

    function optionCodeFragment(value) {
        return text.normalize(value)
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    function canCreateMagentoOptions(root) {
        return !!(root && root.getAttribute('data-can-create-magento-option') === '1');
    }

    function setupErgonodeEntityOptions(root) {
        root.querySelectorAll(
            '[data-role="attribute-side"][data-source-panel="ergo"] [data-role="entity-card"]'
        ).forEach(function (card) {
            var activeToggle = card.querySelector('[data-role="attribute-active-toggle"]');
            var active = activeToggle ? buttons.isPressed(activeToggle) : true;
            var label = card.getAttribute('data-label') || card.getAttribute('data-code') || '';
            var actions = [];

            if (canCreateMagentoOptions(root)) {
                actions.push({
                    active: active,
                    iconClass: 'vea-create-option-icon',
                    label: $t('Utwórz w Magento'),
                    requiresActive: true,
                    role: 'entity-create-magento-option'
                });
            }
            snapshotRemoval.enhance(card, {
                active: active,
                actions: actions,
                removable: !!(root.veaConfig && root.veaConfig.urls && root.veaConfig.urls.delete_snapshot),
                mappingAction: true,
                menuLabel: $t('Opcje wartości') + ': ' + label,
                snapshot: {
                    code: card.getAttribute('data-code') || '',
                    label: label
                },
                toggle: activeToggle
            });
        });
    }

    function actionUrl(config, name) {
        var urls = config && config.urls ? config.urls : {};

        return urls[name] || '';
    }

    function pendingMagentoOptionCode(leftPayload) {
        return [
            'pending',
            optionCodeFragment(leftPayload.code || leftPayload.label || 'option') || 'option',
            Date.now().toString(36),
            Math.random().toString(36).slice(2, 8)
        ].join('_');
    }

    function buildPendingMagentoOption(leftPayload) {
        var label = leftPayload.label || leftPayload.code || '';

        return {
            label: label,
            code: pendingMagentoOptionCode(leftPayload),
            type: 'option',
            scope: $t('po zapisie'),
            source: 'magento',
            pending_create: true,
            create_label: label
        };
    }

    function optionCardHtml(payload) {
        return [
            '<span class="vea-drag-handle" aria-hidden="true"></span>',
            '<div class="vea-card-copy">',
            '<strong>',
            text.escapeHtml(payload.label),
            '</strong>',
            mappingElements.metaHtml(payload),
            '</div>',
            '<button type="button" class="vea-card-toggle" data-role="attribute-active-toggle" aria-label="',
            text.escapeHtml($t('Opcja aktywna do mapowania')),
            '" aria-pressed="true" title="',
            text.escapeHtml($t('Włącz / wyłącz opcję z mapowania')),
            '">',
            '<span aria-hidden="true"></span>',
            '</button>'
        ].join('');
    }

    function emptySlotHtml(side, requiredType, canCreateOption) {
        var html = [
            '<span class="vea-slot-empty-line">',
            '<span class="vea-slot-drop-icon" aria-hidden="true"></span>',
            '<strong>' + text.escapeHtml($t('Przeciągnij opcję')) + '</strong>',
            '</span>'
        ];

        if (requiredType) {
            html.push(mappingElements.typeBadgeHtml(requiredType, 'slot-hint'));

            if (side === 'magento' && canCreateOption) {
                html.push(
                    '<button type="button" class="vea-create-option" data-role="create-magento-option" data-completes-missing-side="1" title="' + text.escapeHtml($t('Utwórz opcję w Magento z opcji Ergonode')) + '" aria-label="' + text.escapeHtml($t('Utwórz opcję w Magento z opcji Ergonode')) + '">',
                    '<span class="vea-create-option-icon" aria-hidden="true"></span>',
                    '<span class="vea-visually-hidden">' + text.escapeHtml($t('Utwórz opcję w Magento')) + '</span>',
                    '</button>'
                );
            }
        } else {
            html.push('<span data-role="slot-hint">' + text.escapeHtml(side === 'magento' ? $t('z Magento') : $t('z Ergonode')) + '</span>');
        }

        return html.join('');
    }

    function unlinkMappingHtml() {
        var label = $t('Usuń mapowanie opcji');

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

    function getSourceCardPayload(card) {
        var activeToggle = card.querySelector('[data-role="attribute-active-toggle"]');
        var isActive = activeToggle ? buttons.isPressed(activeToggle) : true;
        var isMapped = card.getAttribute('data-mapped') === '1';
        var payload;

        if (!isActive || isMapped) {
            return null;
        }

        payload = mappingElements.cardPayload(card);
        payload.origin = 'source';

        return payload;
    }

    function optionCardKey(source, code) {
        return source + '\u0000' + code;
    }

    function indexOptionCard(root, card) {
        var source = card ? card.getAttribute('data-source') || '' : '';
        var code = card ? card.getAttribute('data-code') || '' : '';

        if (!root || !card || !source || !code) {
            return;
        }

        if (!root.veaOptionCardIndex) {
            root.veaOptionCardIndex = Object.create(null);
        }

        root.veaOptionCardIndex[optionCardKey(source, code)] = card;
    }

    function buildOptionCardIndex(root) {
        root.veaOptionCardIndex = Object.create(null);
        root.querySelectorAll('[data-role="entity-card"]').forEach(function (card) {
            indexOptionCard(root, card);
        });
    }

    function findOptionCard(root, source, code) {
        if (!root.veaOptionCardIndex) {
            buildOptionCardIndex(root);
        }

        return root.veaOptionCardIndex[optionCardKey(source, code)] || null;
    }

    function updateOptionCardVisibility(root, card) {
        mappingBoard.updateCardVisibility(root, card);
    }

    function setOptionMapped(root, source, code, mapped) {
        var card = findOptionCard(root, source, code);

        if (!card) {
            return;
        }

        if (!mapped && card.getAttribute('data-pending-create') === '1') {
            delete root.veaOptionCardIndex[optionCardKey(source, code)];
            card.remove();
            return;
        }

        card.setAttribute('data-mapped', mapped ? '1' : '0');
        if (mapped) {
            card.hidden = true;
            card.classList.add('is-filter-hidden', 'is-mapped');
            return;
        }

        updateOptionCardVisibility(root, card);
    }

    function appendMagentoOptionCard(root, payload) {
        var panel = root.querySelector('[data-role="attribute-side"][data-source-panel="magento"]');
        var list = panel ? panel.querySelector('[data-role="attribute-list"]') : null;
        var card;

        if (!panel || !list || !payload || !payload.code) {
            return null;
        }

        card = findOptionCard(root, 'magento', payload.code);

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
        card.setAttribute('data-type', payload.type || 'option');
        card.setAttribute('data-scope', payload.scope || '');
        card.setAttribute('data-pending-create', payload.pending_create ? '1' : '0');
        card.setAttribute('data-create-label', payload.create_label || payload.label || '');
        card.setAttribute('data-search', mappingElements.searchText(payload));
        card.setAttribute('data-mapped', '0');
        card.setAttribute('role', 'group');
        card.setAttribute('tabindex', '0');
        card.setAttribute(
            'aria-label',
            $t('Dodaj opcję do mapowania: %1').replace('%1', payload.label || payload.code || '')
        );
        card.setAttribute('data-sort-index', String(panel.veaNextSortIndex || 0));
        panel.veaNextSortIndex = (panel.veaNextSortIndex || 0) + 1;
        card.hidden = true;
        card.innerHTML = optionCardHtml(payload);

        indexOptionCard(root, card);
        bindAttributeCardDrag(root, card);
        updateSidePanel(panel, true);

        return card;
    }

    function updateSidePanel(panel, shouldSort) {
        mappingBoard.updateSidePanel(panel, {
            sort: !!shouldSort
        });
    }

    function updateEmptySlotHint(slot) {
        var opposite = getOppositeSlot(slot);
        var root = slot ? slot.closest('.vea-mapping') : null;

        if (!slot || hasSlotOption(slot)) {
            return;
        }

        slot.innerHTML = emptySlotHtml(getSlotSide(slot), getSlotType(opposite), canCreateMagentoOptions(root));
    }

    function setSlotEmpty(slot) {
        var side = getSlotSide(slot);
        var root = slot.closest('.vea-mapping');

        slot.classList.add('is-empty');
        slot.classList.remove('is-drop-ready', 'is-drop-rejected');
        slot.setAttribute('data-label', '');
        slot.setAttribute('data-code', '');
        slot.setAttribute('data-type', '');
        slot.setAttribute('data-scope', '');
        slot.setAttribute('data-pending-create', '0');
        slot.setAttribute('data-create-label', '');
        slot.removeAttribute('draggable');
        slot.innerHTML = emptySlotHtml(side, getSlotType(getOppositeSlot(slot)), canCreateMagentoOptions(root));
        updateEmptySlotHint(slot);
    }

    function setSlotFilled(slot, payload) {
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
            mappingBoard.updateMappingRowVisibility(row, mappingBoard.getMappingFilterState(root));
        }
    }

    function refreshRowAfterEdit(row) {
        var left = row.querySelector('[data-role="pair-slot"][data-side="ergo"]');
        var right = row.querySelector('[data-role="pair-slot"][data-side="magento"]');
        var leftFilled = hasSlotOption(left);
        var rightFilled = hasSlotOption(right);
        var leftType = getSlotType(left);
        var rightType = getSlotType(right);

        row.querySelectorAll('[data-role="pair-slot"]').forEach(updateEmptySlotHint);
        updateRowSearchData(row);

        if (leftFilled && rightFilled && text.normalize(leftType) !== text.normalize(rightType)) {
            setRowStatus(row, 'error');
            showRowMessage(row, $t('Nie można połączyć: opcje pochodzą z niezgodnych typów.'));
            return;
        }

        hideRowMessage(row);
        setRowStatus(row, leftFilled && rightFilled ? 'manual' : 'draft');
    }

    function updateMappingFilter(root) {
        mappingBoard.updateMappingFilter(root, false);
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
                message: $t('Nie rozpoznano przeciąganej opcji.')
            };
        }

        if (payload.origin === 'mapping') {
            return {
                allowed: false,
                message: $t('Upuść opcję w bocznej strefie usuwania z mapowania.')
            };
        }

        if (payload.source !== side) {
            return {
                allowed: false,
                message: $t('Ten slot przyjmuje tylko opcje %1.')
                    .replace('%1', mappingElements.sourceLabel(side))
            };
        }

        if (oppositeType && text.normalize(oppositeType) !== text.normalize(payload.type)) {
            return {
                allowed: false,
                message: $t('Ta opcja nie pasuje do wymaganego typu.')
            };
        }

        return {
            allowed: true,
            message: ''
        };
    }

    function clearMappedDragState(root, keepDisablePanelState) {
        var state = dragDrop.state(root);

        root.classList.remove('is-dragging-from-mapping', 'is-dragging-ergo', 'is-dragging-magento');
        if (state.mappedElement) {
            state.mappedElement.classList.remove('is-dragging');
        }
        if (!keepDisablePanelState) {
            clearDisablePanelStates(root);
        }
        state.payload = null;
        state.mappedElement = null;
    }

    function setSideDragState(root, source, active) {
        root.classList.toggle('is-dragging-to-mapping', active);
        root.classList.toggle('is-dragging-source-ergo', active && source === 'ergo');
        root.classList.toggle('is-dragging-source-magento', active && source === 'magento');
    }

    function clearSideDragState(root, keepCreatePanelState) {
        var state = dragDrop.state(root);

        root.classList.remove('is-dragging-to-mapping', 'is-dragging-source-ergo', 'is-dragging-source-magento');
        if (state.sourceElement) {
            state.sourceElement.classList.remove('is-dragging');
        }
        if (!keepCreatePanelState) {
            clearCreatePanelStates(root);
        }
        state.payload = null;
        state.sourceElement = null;
    }

    function createEmptyMappingRow() {
        var row = document.createElement('article');

        row.className = 'vea-pair-row vea-status-tone-warning';
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

        row = createEmptyMappingRow();
        list.insertBefore(row, list.firstChild);
        slot = row.querySelector('[data-role="pair-slot"][data-side="' + payload.source + '"]');

        if (slot) {
            setSlotFilled(slot, payload);
            setOptionMapped(root, payload.source, payload.code, true);
            refreshRowAfterEdit(row);
            mappingElements.markNewMapping(row, payload.source);
        }

        bindMappingRow(root, row);
        return row;
    }

    function fillComplementaryMappingRow(root, payload) {
        var row = mappingBoard.findComplementaryRow(root, payload.source, function (slot) {
            return getDropDecision(slot, payload).allowed;
        });
        var slot;

        if (!row) {
            return null;
        }

        slot = row.querySelector('[data-role="pair-slot"][data-side="' + payload.source + '"]');
        setSlotFilled(slot, payload);
        setOptionMapped(root, payload.source, payload.code, true);
        refreshRowAfterEdit(row);
        mappingElements.markNewMapping(row, payload.source);
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

    function getAutoMatchPayloads(root, source) {
        var payloads = Array.prototype.slice.call(
            root.querySelectorAll('[data-role="entity-card"][data-source="' + source + '"]')
        ).filter(function (card) {
            var activeToggle = card.querySelector('[data-role="attribute-active-toggle"]');
            var isActive = activeToggle ? buttons.isPressed(activeToggle) : true;
            var isMapped = card.getAttribute('data-mapped') === '1';

            return isActive && !isMapped;
        }).map(function (card) {
            return mappingElements.cardPayload(card);
        });

        root.querySelectorAll('[data-role="mapping-row"]').forEach(function (row) {
            var slot = row.querySelector('[data-role="pair-slot"][data-side="' + source + '"]');
            var opposite = slot ? getOppositeSlot(slot) : null;
            var payload = mappingElements.slotPayload(slot);

            if (payload && !hasSlotOption(opposite)) {
                payloads.push(payload);
            }
        });

        return payloads;
    }

    function createMatchedMappingRow(root, leftPayload, rightPayload) {
        var row;
        var leftSlot;
        var rightSlot;

        if (!leftPayload || !rightPayload) {
            return null;
        }

        row = createEmptyMappingRow();
        leftSlot = row.querySelector('[data-role="pair-slot"][data-side="ergo"]');
        rightSlot = row.querySelector('[data-role="pair-slot"][data-side="magento"]');

        if (leftSlot && rightSlot) {
            setSlotFilled(leftSlot, leftPayload);
            setSlotFilled(rightSlot, rightPayload);
            setOptionMapped(root, leftPayload.source, leftPayload.code, true);
            setOptionMapped(root, rightPayload.source, rightPayload.code, true);
            refreshRowAfterEdit(row);
            mappingElements.markNewMapping(row, 'magento');
        }

        return row;
    }

    function findDraftRowByPayload(root, payload) {
        var rows = root.querySelectorAll('[data-role="mapping-row"]');
        var result = null;

        Array.prototype.some.call(rows, function (row) {
            var slot = row.querySelector(
                '[data-role="pair-slot"][data-side="' + payload.source + '"]'
            );
            var current = mappingElements.slotPayload(slot);
            var opposite = slot ? getOppositeSlot(slot) : null;

            if (current && current.code === payload.code && !hasSlotOption(opposite)) {
                result = row;
                return true;
            }

            return false;
        });

        return result;
    }

    function completeAutoMatchedDraft(root, leftPayload, rightPayload) {
        var leftRow = findDraftRowByPayload(root, leftPayload);
        var rightRow = findDraftRowByPayload(root, rightPayload);
        var row = leftRow || rightRow;
        var payload = leftRow ? rightPayload : leftPayload;
        var slot;

        if (!row) {
            return null;
        }

        slot = row.querySelector('[data-role="pair-slot"][data-side="' + payload.source + '"]');
        if (!slot || hasSlotOption(slot)) {
            return null;
        }

        setSlotFilled(slot, payload);
        setOptionMapped(root, leftPayload.source, leftPayload.code, true);
        setOptionMapped(root, rightPayload.source, rightPayload.code, true);
        if (leftRow && rightRow && leftRow !== rightRow) {
            rightRow.remove();
        }
        refreshRowAfterEdit(row);
        mappingElements.markNewMapping(row, payload.source);

        return row;
    }

    function applyAutoMatches(root, matches) {
        var list = root.querySelector('[data-role="mapping-list"]');
        var fragment;
        var newRows = [];
        var changedRows = [];

        if (!list) {
            return 0;
        }

        fragment = document.createDocumentFragment();

        (Array.isArray(matches) ? matches : []).forEach(function (match) {
            var row = completeAutoMatchedDraft(root, match.left, match.right);

            if (row) {
                changedRows.push(row);
                return;
            }

            row = createMatchedMappingRow(root, match.left, match.right);
            if (!row) {
                return;
            }
            fragment.insertBefore(row, fragment.firstChild);
            newRows.push(row);
        });

        if (newRows.length) {
            list.insertBefore(fragment, list.firstChild);
            newRows.forEach(function (row) {
                bindMappingRow(root, row);
            });
        }
        if (newRows.length || changedRows.length) {
            updateMappingFilter(root);
            scheduleSaveStateUpdate(root);
        }

        return newRows.length + changedRows.length;
    }

    function collectAutoMatchPayload(root, config) {
        return {
            attribute_mapping_id: config.attribute_mapping_id || 0,
            ergonode_options: getAutoMatchPayloads(root, 'ergo'),
            magento_options: getAutoMatchPayloads(root, 'magento')
        };
    }

    function bindPairSlot(root, slot) {
        if (!root.veaWorkspace.claim(slot, 'pair-slot')) {
            return;
        }

        updateEmptySlotHint(slot);

        root.veaWorkspace.listen(slot, 'dragstart', function (event) {
            var payload = mappingElements.slotPayload(slot);

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

            if (acceptsSourceDrop(payload) && hasSlotOption(slot)) {
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

            if (acceptsSourceDrop(payload) && hasSlotOption(slot)) {
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

            if (previousPayload) {
                setOptionMapped(root, previousPayload.source, previousPayload.code, false);
            }

            setSlotFilled(slot, payload);
            setOptionMapped(root, payload.source, payload.code, true);
            row.classList.remove('is-type-error');
            refreshRowAfterEdit(row);
        });
    }

    function initPairSlots(root) {
        root.querySelectorAll('[data-role="pair-slot"]').forEach(function (slot) {
            bindPairSlot(root, slot);
        });
    }

    function bindUnlinkButton(root, button) {
        if (!root.veaWorkspace.claim(button, 'unlink-button')) {
            return;
        }

        root.veaWorkspace.listen(button, 'click', function () {
            var row = button.closest('[data-role="mapping-row"]');

            if (!row) {
                return;
            }

            row.querySelectorAll('[data-role="pair-slot"]').forEach(function (slot) {
                var payload = mappingElements.slotPayload(slot);

                if (payload) {
                    setOptionMapped(root, payload.source, payload.code, false);
                }
            });

            row.remove();
        });
    }

    function initUnlinkButtons(root) {
        root.querySelectorAll('[data-role="unlink-mapping"]').forEach(function (button) {
            bindUnlinkButton(root, button);
        });
    }

    function bindMappingRow(root, row) {
        var unlinkButton = row.querySelector('[data-role="unlink-mapping"]');

        row.querySelectorAll('[data-role="pair-slot"]').forEach(function (slot) {
            bindPairSlot(root, slot);
        });

        if (unlinkButton) {
            bindUnlinkButton(root, unlinkButton);
        }
    }

    function initCreateMagentoOption(root) {
        root.veaWorkspace.delegate('click', '[data-role="create-magento-option"]', function (event, button) {
            var slot;
            var row;
            var leftSlot;
            var leftPayload;

            slot = button.closest('[data-role="pair-slot"]');
            row = button.closest('[data-role="mapping-row"]');
            leftSlot = row ? row.querySelector('[data-role="pair-slot"][data-side="ergo"]') : null;
            leftPayload = mappingElements.slotPayload(leftSlot);

            if (!slot || getSlotSide(slot) !== 'magento' || hasSlotOption(slot)) {
                return;
            }

            if (!leftPayload) {
                root.veaContext.message.show('error', $t('Najpierw wybierz opcję Ergonode.'));
                return;
            }

            stageMagentoOption(root, row, buildPendingMagentoOption(leftPayload));
            root.veaContext.message.show(
                'success',
                $t('Opcja zostanie utworzona w Magento po zapisaniu mapowania.')
            );
        });
    }

    function stageMagentoOption(root, row, option) {
        var slot = row
            ? row.querySelector('[data-role="pair-slot"][data-side="magento"]')
            : null;

        if (!slot || hasSlotOption(slot) || !option || !option.code) {
            return false;
        }

        appendMagentoOptionCard(root, option);
        setSlotFilled(slot, option);
        setOptionMapped(root, 'magento', option.code, true);
        refreshRowAfterEdit(row);
        mappingElements.markNewMapping(row, 'magento');

        return true;
    }

    function normalizeRowAfterSlotRemoval(row) {
        if (!row) {
            return;
        }

        if (isRowEmpty(row)) {
            row.remove();
            return;
        }

        refreshRowAfterEdit(row);
    }

    function removeMappedSlot(root, slot, payload) {
        var row = slot ? slot.closest('[data-role="mapping-row"]') : null;

        if (!slot || !payload) {
            return;
        }

        setOptionMapped(root, payload.source, payload.code, false);
        setSlotEmpty(slot);
        normalizeRowAfterSlotRemoval(row);
    }

    function bindAttributeCardDrag(root, card) {
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
            setSideDragState(root, payload.source, true);
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
    }

    function initDrag(root) {
        root.querySelectorAll('[data-role="entity-card"]').forEach(function (card) {
            bindAttributeCardDrag(root, card);
        });

        root.querySelectorAll('[data-role="mapping-panel"]').forEach(function (panel) {
            if (!root.veaWorkspace.claim(panel, 'mapping-drop-panel')) {
                return;
            }

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
            if (!root.veaWorkspace.claim(panel, 'disable-drop-panel')) {
                return;
            }

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

    function runOptionAction(root, button, action, messages) {
        var config = root.veaConfig || {};
        var panel = button.closest('[data-role="attribute-side"]');

        if (button.disabled) {
            return;
        }
        if (hasUnsavedChanges(root)) {
            root.veaContext.message.show(
                'error',
                messages.unsaved
            );
            return;
        }

        if (panel) {
            panel.classList.add('is-refreshing');
        }
        buttons.setBusy(button, true, {busyClass: 'is-working'});
        request.post(actionUrl(config, action), config, {
            attribute_mapping_id: String(config.attribute_mapping_id || 0)
        }).then(function (response) {
            var successMessage = response.message || messages.success;

            if (root.classList.contains('vea-option-mapping-embedded')) {
                root.dispatchEvent(new CustomEvent('vea:option-mapping-reload', {
                    bubbles: true,
                    detail: {message: successMessage}
                }));
                return;
            }

            root.veaContext.message.show('success', successMessage);
            window.setTimeout(function () {
                window.location.reload();
            }, 350);
        }).catch(function (error) {
            root.veaContext.message.error(
                messages.errorTitle,
                error,
                messages.errorFallback
            );
            if (panel) {
                panel.classList.remove('is-refreshing');
            }
            buttons.setBusy(button, false, {busyClass: 'is-working'});
        });
    }

    function refreshOptions(root, button) {
        runOptionAction(root, button, 'refresh', {
            unsaved: $t('Save manual changes before refreshing options.'),
            success: $t('Option data has been refreshed from Ergonode.'),
            errorTitle: $t('Unable to refresh options'),
            errorFallback: $t('Unable to refresh option data from Ergonode.')
        });
    }

    function synchronizeOptions(root, button) {
        runOptionAction(root, button, 'sync', {
            unsaved: $t('Save manual changes before synchronizing options.'),
            success: $t('Options have been synchronized.'),
            errorTitle: $t('Unable to synchronize options'),
            errorFallback: $t('Unable to synchronize options with Magento.')
        });
    }

    function initRefreshErgonode(root) {
        var button = root.querySelector('[data-role="refresh-ergonode"]');

        if (!button) {
            return;
        }

        root.veaWorkspace.listen(button, 'click', function () {
            refreshOptions(root, button);
        });
    }

    function initSynchronizeErgonode(root) {
        var button = root.querySelector('[data-role="sync-ergonode"]');

        if (!button) {
            return;
        }

        root.veaWorkspace.listen(button, 'click', function () {
            synchronizeOptions(root, button);
        });
    }

    function initAutoMatch(root) {
        var button = root.querySelector('[data-role="auto-match"]');
        var label = button ? button.querySelector('[data-role="auto-match-label"]') : null;
        var defaultText = label ? label.textContent : '';

        if (!button) {
            return;
        }

        root.veaWorkspace.listen(button, 'click', function () {
            var config = root.veaConfig || {};

            if (button.disabled) {
                return;
            }

            buttons.setBusy(button, true, {busyClass: 'is-working'});
            request.post(actionUrl(config, 'auto_match'), config, {
                payload: JSON.stringify(collectAutoMatchPayload(root, config))
            }).then(function (response) {
                var matched = applyAutoMatches(root, response.matches);

                buttons.setBusy(button, false, {busyClass: 'is-working'});
                if (label) {
                    label.textContent = matched
                        ? $t('Dopasowano %1').replace('%1', matched)
                        : $t('Brak dopasowań');
                }

                window.setTimeout(function () {
                    if (label) {
                        label.textContent = defaultText;
                    }
                }, 1200);
            }).catch(function (error) {
                root.veaContext.message.error(
                    $t('Nie udało się automatycznie dopasować opcji'),
                    error,
                    $t('Nie udało się przygotować propozycji mapowania opcji.')
                );
                buttons.setBusy(button, false, {busyClass: 'is-working'});
                if (label) {
                    label.textContent = defaultText;
                }
            });
        });
    }

    function initSaveAction(root) {
        var button = root.querySelector('[data-role="save-mapping"]');

        if (!button) {
            return;
        }

        root.veaWorkspace.listen(button, 'click', function () {
            var config = root.veaConfig || {};

            if (button.disabled) {
                return;
            }

            saveWithFeedback(root, config).then(function () {
                window.setTimeout(function () {
                    window.location.reload();
                }, 500);
            }).catch(function () {});
        });
    }

    function saveWithFeedback(root, config) {
        var button = root.querySelector('[data-role="save-mapping"]');
        var label = button ? button.querySelector('[data-role="save-mapping-label"]') : null;
        var defaultText = label ? label.textContent : '';

        if (button) {
            buttons.setBusy(button, true, {busyClass: 'is-saved'});
        }
        if (label) {
            label.textContent = $t('Zapisywanie');
        }

        return saveCurrentMappings(root, config).then(function (response) {
            updateSaveState(root);
            if (label) {
                label.textContent = $t('Zapisano');
            }

            return response;
        }).catch(function (error) {
            root.veaContext.message.error(
                $t('Nie udało się zapisać mapowania opcji'),
                error,
                $t('Nie udało się zapisać mapowań opcji.')
            );
            if (button) {
                buttons.setBusy(button, false, {busyClass: 'is-saved'});
            }
            updateSaveState(root);
            if (label) {
                label.textContent = defaultText;
            }

            throw error;
        });
    }

    function saveCurrentMappings(root, config) {
        var payload = collectSavePayload(root, config || root.veaConfig || {});

        return request.post(actionUrl(config, 'save'), config || {}, {
            payload: JSON.stringify(payload)
        }).then(function (response) {
            mappingSaveResult.persist('option', response);
            root.veaContext.dirty.capture();

            return response;
        });
    }

    function collectSavePayload(root, config) {
        return {
            attribute_mapping_id: config.attribute_mapping_id || 0,
            mappings: mappingBoard.collectMappings(root, false),
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
        var busy = button && button.getAttribute('aria-busy') === 'true';

        if (button && !busy) {
            button.disabled = !hasUnsavedChanges(root);
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

        if (typeof MutationObserver !== 'undefined') {
            observer = new MutationObserver(function () {
                scheduleSaveStateUpdate(root);
            });
            observer.observe(root, {
                attributeFilter: [
                    'aria-pressed',
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

    function updateAllSidePanels(root, shouldSort) {
        root.querySelectorAll('[data-role="attribute-side"]').forEach(function (panel) {
            updateSidePanel(panel, shouldSort);
        });
    }

    function initOptionContextSwitcher(root) {
        var select = root.querySelector('[data-role="option-context-select"]');
        var loader = root.querySelector('[data-role="option-context-loader"]');
        var currentValue = select ? select.value : '';

        if (!select) {
            return;
        }

        root.veaWorkspace.listen(select, 'change', function () {
            var config = root.veaConfig || {};
            var contextUrl = actionUrl(config, 'context');
            var url = contextUrl
                ? contextUrl.replace('__mapping_id__', encodeURIComponent(select.value))
                : '';

            if (select.value === currentValue) {
                return;
            }

            if (url) {
                root.veaUnsavedNavigation.request({
                    navigate: function () {
                        currentValue = select.value;
                        root.classList.add('is-context-loading');
                        root.setAttribute('aria-busy', 'true');
                        select.disabled = true;

                        if (loader) {
                            loader.setAttribute('aria-hidden', 'false');
                        }

                        window.requestAnimationFrame(function () {
                            window.location.href = url;
                        });
                    },
                    onCancel: function () {
                        select.value = currentValue;
                    },
                    onSaveError: function () {
                        select.value = currentValue;
                    }
                });
            }
        });
    }

    return function (config, element) {
        return workspace.mount(element, function (scope) {
            element.veaWorkspace = scope;
            element.veaConfig = config || {};
            workspaceContext.create(scope, element, {
                serialize: function () {
                    return collectSavePayload(element, element.veaConfig);
                }
            });
            mappingSaveResult.show(
                element.veaContext.message,
                mappingSaveResult.consume('option')
            );
            element.veaUnsavedNavigation = unsavedNavigation.bind(scope, {
                isDirty: function () {
                    return hasUnsavedChanges(element);
                },
                navigate: function (url) {
                    window.location.assign(url);
                },
                save: function () {
                    return saveWithFeedback(element, element.veaConfig);
                }
            });
            setupErgonodeEntityOptions(element);
            snapshotRemoval.bind(scope, element, config, {
                data: function () {
                    return {attribute_mapping_id: config.attribute_mapping_id || 0};
                },
                isDirty: function () {
                    return hasUnsavedChanges(element);
                },
                message: element.veaContext.message
            });
            scope.cleanup(function () {
                dragDrop.clear(element);
                delete element.veaUnsavedNavigation;
                delete element.veaWorkspace;
            });

            element.querySelectorAll('[data-role="attribute-side"]').forEach(function (panel) {
                mappingBoard.setupSidePanel(panel);
                search.bindSort(scope, panel, {
                    toggleSelector: '[data-role="attribute-sort-toggle"]',
                    directionSelector: '[data-role="attribute-sort-direction"]',
                    directionLabelSelector: '[data-role="attribute-sort-direction-label"]',
                    labelSelector: '[data-role="attribute-sort-label"]',
                    defaultValue: 'label',
                    values: ['label', 'code'],
                    options: {
                        label: {label: $t('Nazwa'), ariaLabel: $t('Sortuj po etykiecie')},
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

            buildOptionCardIndex(element);
            sourceBulkTransfer.bind(scope, element, {
                transfer: function (card) {
                    var payload = getSourceCardPayload(card);

                    return payload ? !!addSourcePayloadToMapping(element, payload) : false;
                }
            });
            search.bind(scope, {
                inputSelector: '[data-role="attribute-search"]',
                events: ['input'],
                update: function (query, input) {
                    updateSidePanel(input.closest('[data-role="attribute-side"]'));
                }
            });
            scope.delegate('click', '[data-role="attribute-active-toggle"]', function (event, button) {
                var card = button.closest('[data-role="entity-card"]');

                buttons.togglePressed(button);
                updateSidePanel(button.closest('[data-role="attribute-side"]'));
                snapshotRemoval.setActiveState(card, buttons.isPressed(button));
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
            scope.delegate('click', '[data-role="entity-create-magento-option"]', function (event, button) {
                var card = button.closest('[data-role="entity-card"]');
                var payload = getSourceCardPayload(card);
                var row;
                var createButton;

                event.preventDefault();
                event.stopPropagation();
                if (!payload) {
                    return;
                }

                row = addSourcePayloadToMapping(element, payload, false);
                createButton = row ? row.querySelector('[data-role="create-magento-option"]') : null;
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
                events: ['input'],
                update: function () {
                    updateMappingFilter(element);
                }
            });
            mappingBoard.initializeVisibility(element);
            updateAllSidePanels(element);
            updateMappingFilter(element);
            initDrag(element);
            initPairSlots(element);
            initUnlinkButtons(element);
            initCreateMagentoOption(element);
            initRefreshErgonode(element);
            initSynchronizeErgonode(element);
            initAutoMatch(element);
            completeMissing(element);
            initSaveAction(element);
            initOptionContextSwitcher(element);
            element.veaContext.dirty.capture();
            initSaveState(element);
        });
    };
});
