define([
    'Ergonode_CoreAdminUi/js/text',
    'Ergonode_CoreAdminUi/js/mapping-elements',
    'Ergonode_CoreAdminUi/js/buttons',
    'Ergonode_CoreAdminUi/js/search',
    'Ergonode_CoreAdminUi/js/drag-drop',
    'Ergonode_CoreAdminUi/js/visibility-toggle'
], function (text, mappingElements, buttons, search, dragDrop, visibilityToggle) {
    'use strict';

    function getSlotSide(slot) {
        return slot.getAttribute('data-side') === 'magento' ? 'magento' : 'ergo';
    }

    function getOppositeSlot(slot) {
        var row = slot.closest('[data-role="mapping-row"]');
        var side = getSlotSide(slot);
        var oppositeSide = side === 'magento' ? 'ergo' : 'magento';

        return row ? row.querySelector('[data-role="pair-slot"][data-side="' + oppositeSide + '"]') : null;
    }

    function getSlotType(slot) {
        return slot ? slot.getAttribute('data-type') || '' : '';
    }

    function hasSlotValue(slot) {
        return !!(slot && slot.getAttribute('data-code'));
    }

    function clearSlotDropState(slot) {
        slot.classList.remove('is-drop-ready', 'is-drop-rejected');
    }

    function setMappedDragState(root, source, active) {
        root.classList.toggle('is-dragging-from-mapping', active);
        root.classList.toggle('is-dragging-ergo', active && source === 'ergo');
        root.classList.toggle('is-dragging-magento', active && source === 'magento');
    }

    function clearDisablePanelStates(root) {
        root.querySelectorAll('[data-role="attribute-side"]').forEach(function (panel) {
            var zone = panel.querySelector('[data-role="disable-drop-zone"]');

            panel.classList.remove('is-disable-drop-ready', 'is-disable-drop-active');
            if (zone) {
                zone.classList.remove('is-drop-ready', 'is-drop-active');
            }
        });
    }

    function acceptsMappedDrop(panel, payload) {
        var panelSource = panel ? panel.getAttribute('data-source-panel') : '';

        return !!(payload && payload.origin === 'mapping' && payload.source === panelSource);
    }

    function setDisablePanelReady(panel, ready) {
        var zone = panel.querySelector('[data-role="disable-drop-zone"]');

        panel.classList.toggle('is-disable-drop-ready', ready);
        if (zone) {
            zone.classList.toggle('is-drop-ready', ready);
        }
    }

    function setDisablePanelActive(panel) {
        var zone = panel.querySelector('[data-role="disable-drop-zone"]');

        panel.classList.remove('is-disable-drop-ready');
        panel.classList.add('is-disable-drop-active');
        if (zone) {
            zone.classList.remove('is-drop-ready');
            zone.classList.add('is-drop-active');
        }
    }

    function clearCreatePanelStates(root) {
        root.querySelectorAll('[data-role="mapping-panel"]').forEach(function (panel) {
            var zone = panel.querySelector('[data-role="create-drop-zone"]');

            panel.classList.remove('is-create-drop-ready', 'is-create-drop-active');
            if (zone) {
                zone.classList.remove('is-drop-ready', 'is-drop-active');
            }
        });
    }

    function acceptsSourceDrop(payload) {
        return !!(payload && payload.code && payload.origin !== 'mapping' &&
            (payload.source === 'ergo' || payload.source === 'magento'));
    }

    function setCreatePanelReady(panel, ready) {
        var zone = panel.querySelector('[data-role="create-drop-zone"]');

        panel.classList.toggle('is-create-drop-ready', ready);
        if (zone) {
            zone.classList.toggle('is-drop-ready', ready);
        }
    }

    function setCreatePanelActive(panel) {
        var zone = panel.querySelector('[data-role="create-drop-zone"]');

        panel.classList.remove('is-create-drop-ready');
        panel.classList.add('is-create-drop-active');
        if (zone) {
            zone.classList.remove('is-drop-ready');
            zone.classList.add('is-drop-active');
        }
    }

    function setupSidePanel(panel) {
        var cards = panel.querySelectorAll('[data-role="entity-card"]');

        cards.forEach(function (card, index) {
            card.setAttribute('data-sort-index', index.toString());
        });
        panel.veaNextSortIndex = cards.length;
    }

    function sortCards(panel) {
        var list = panel.querySelector('[data-role="attribute-list"]');
        var sortToggle = panel.querySelector('[data-role="attribute-sort-toggle"]');
        var directionButton = panel.querySelector('[data-role="attribute-sort-direction"]');
        var sortBy = sortToggle ? sortToggle.getAttribute('data-sort-value') : 'label';
        var sortDirection = directionButton ? directionButton.getAttribute('data-direction') : 'asc';

        if (!list) {
            return;
        }

        search.sort(list, '[data-role="entity-card"]', {
            value: function (card) {
                return card.getAttribute('data-' + sortBy);
            },
            direction: sortDirection,
            tie: function (first, second) {
                return Number(first.getAttribute('data-sort-index') || 0) -
                    Number(second.getAttribute('data-sort-index') || 0);
            }
        });
    }

    function visibilityControl(root, panel) {
        return (panel ? panel.querySelector('[data-role="visibility-toggle"]') : null) ||
            (root ? root.querySelector('[data-role="visibility-toggle"]') : null);
    }

    function showsOmitted(root, panel) {
        var toggle = visibilityControl(root, panel);

        return visibilityToggle.isVisible(toggle);
    }

    function syncPanelVisibility(panel) {
        var toggle = panel ? panel.querySelector('[data-role="visibility-toggle"]') : null;
        var available = panel && Array.prototype.some.call(
            panel.querySelectorAll('[data-role="entity-card"] [data-role="attribute-active-toggle"]'),
            function (activeToggle) {
                return !buttons.isPressed(activeToggle);
            }
        );

        if (!toggle) {
            return false;
        }
        if (!available) {
            visibilityToggle.setVisible(toggle, false);
        }
        toggle.disabled = !available;

        return available;
    }

    function initializeVisibility(root) {
        visibilityToggle.initialize(root);
    }

    function toggleVisibility(button) {
        return visibilityToggle.toggle(button);
    }

    function updateCardVisibility(root, card) {
        var panel = card ? card.closest('[data-role="attribute-side"]') : null;
        var queryInput = panel ? panel.querySelector('[data-role="attribute-search"]') : null;
        var query = text.normalize(queryInput ? queryInput.value : '');
        var activeToggle = card ? card.querySelector('[data-role="attribute-active-toggle"]') : null;
        var isActive = activeToggle ? buttons.isPressed(activeToggle) : true;
        var isMapped = card ? card.getAttribute('data-mapped') === '1' : false;
        var matchesQuery = card ? text.normalize(card.getAttribute('data-search')).indexOf(query) !== -1 : false;
        var visible = matchesQuery && !isMapped && (isActive || showsOmitted(root, panel));

        if (!card) {
            return;
        }

        card.hidden = !visible;
        card.classList.toggle('is-filter-hidden', !visible);
        card.classList.toggle('is-inactive', !isActive);
        card.classList.toggle('is-mapped', isMapped);
    }

    function updateSidePanel(panel, options) {
        var root = panel.closest('.vea-mapping');

        options = options || {};
        if (options.sort) {
            sortCards(panel);
        }
        syncPanelVisibility(panel);
        panel.querySelectorAll('[data-role="entity-card"]').forEach(function (card) {
            updateCardVisibility(root, card);
        });
    }

    function getStatusTone(rowStatus) {
        if (rowStatus === 'error') {
            return 'error';
        }

        if (rowStatus === 'draft') {
            return 'warning';
        }

        return 'ok';
    }

    function updateRowSearchData(row) {
        var parts = [];

        row.querySelectorAll('[data-role="pair-slot"]').forEach(function (slot) {
            parts.push(slot.getAttribute('data-label') || '');
            parts.push(slot.getAttribute('data-code') || '');
            parts.push(slot.getAttribute('data-type') || '');
            parts.push(slot.getAttribute('data-scope') || '');
        });

        row.setAttribute('data-search', parts.join(' '));
    }

    function getMappingFilterState(root) {
        var searchInput = root.querySelector('[data-role="mapping-search"]');

        return {
            query: text.normalize(searchInput ? searchInput.value : '')
        };
    }

    function updateMappingRowVisibility(row, filterState) {
        var matchesQuery = !filterState.query ||
            text.normalize(row.getAttribute('data-search')).indexOf(filterState.query) !== -1;
        var hidden = !matchesQuery;

        row.hidden = hidden;
        row.classList.toggle('is-mapping-filter-hidden', hidden);
    }

    function updateMappingFilter(root, refreshSearchData) {
        var filterState = getMappingFilterState(root);

        root.querySelectorAll('[data-role="mapping-row"]').forEach(function (row) {
            if (refreshSearchData) {
                updateRowSearchData(row);
            }
            updateMappingRowVisibility(row, filterState);
        });
    }

    function showRowMessage(row, message) {
        var messageBox = row.querySelector('[data-role="pair-message"]');

        if (!messageBox) {
            return;
        }

        messageBox.textContent = message;
        messageBox.hidden = false;
    }

    function hideRowMessage(row) {
        var messageBox = row.querySelector('[data-role="pair-message"]');

        if (messageBox) {
            messageBox.hidden = true;
            messageBox.textContent = '';
        }
    }

    function getDragPayload(root, event, mimeType) {
        var state = dragDrop.state(root);

        if (state.payload) {
            return state.payload;
        }

        try {
            return dragDrop.read(event.dataTransfer, mimeType);
        } catch (error) {
            return null;
        }
    }

    function isRowEmpty(row) {
        return !hasSlotValue(row.querySelector('[data-role="pair-slot"][data-side="ergo"]')) &&
            !hasSlotValue(row.querySelector('[data-role="pair-slot"][data-side="magento"]'));
    }

    function findComplementaryRow(root, source, acceptsSlot) {
        var ownSide = source === 'magento' ? 'magento' : 'ergo';
        var oppositeSide = ownSide === 'magento' ? 'ergo' : 'magento';
        var matches = [];

        root.querySelectorAll('[data-role="mapping-row"]').forEach(function (row) {
            var ownSlot = row.querySelector('[data-role="pair-slot"][data-side="' + ownSide + '"]');
            var oppositeSlot = row.querySelector(
                '[data-role="pair-slot"][data-side="' + oppositeSide + '"]'
            );

            if (ownSlot && oppositeSlot && !hasSlotValue(ownSlot) && hasSlotValue(oppositeSlot)
                && (!acceptsSlot || acceptsSlot(ownSlot, row))) {
                matches.push(row);
            }
        });

        return matches.length === 1 ? matches[0] : null;
    }

    function collectMappings(root, unique) {
        var mappings = [];
        var seen = Object.create(null);

        root.querySelectorAll('[data-role="mapping-row"]').forEach(function (row) {
            var left = mappingElements.slotPayload(row.querySelector('[data-role="pair-slot"][data-side="ergo"]'));
            var right = mappingElements.slotPayload(row.querySelector('[data-role="pair-slot"][data-side="magento"]'));
            var key;

            if (!left && !right) {
                return;
            }

            key = [left ? left.code : '', right ? right.code : ''].join('|');
            if (unique && seen[key]) {
                return;
            }

            seen[key] = true;
            mappings.push({left: left, right: right});
        });

        return mappings;
    }

    function collectVisibility(root) {
        var visibility = [];

        root.querySelectorAll('[data-role="entity-card"]').forEach(function (card) {
            var activeToggle = card.querySelector('[data-role="attribute-active-toggle"]');

            if (card.getAttribute('data-pending-create') === '1') {
                return;
            }

            visibility.push({
                source: card.getAttribute('data-source') || '',
                code: card.getAttribute('data-code') || '',
                active: activeToggle ? buttons.isPressed(activeToggle) : true
            });
        });

        return visibility;
    }

    return {
        acceptsMappedDrop: acceptsMappedDrop,
        acceptsSourceDrop: acceptsSourceDrop,
        clearCreatePanelStates: clearCreatePanelStates,
        clearDisablePanelStates: clearDisablePanelStates,
        clearSlotDropState: clearSlotDropState,
        collectMappings: collectMappings,
        collectVisibility: collectVisibility,
        findComplementaryRow: findComplementaryRow,
        getDragPayload: getDragPayload,
        getMappingFilterState: getMappingFilterState,
        getOppositeSlot: getOppositeSlot,
        getSlotSide: getSlotSide,
        getSlotType: getSlotType,
        getStatusTone: getStatusTone,
        hasSlotValue: hasSlotValue,
        hideRowMessage: hideRowMessage,
        initializeVisibility: initializeVisibility,
        isRowEmpty: isRowEmpty,
        setCreatePanelActive: setCreatePanelActive,
        setCreatePanelReady: setCreatePanelReady,
        setDisablePanelActive: setDisablePanelActive,
        setDisablePanelReady: setDisablePanelReady,
        setMappedDragState: setMappedDragState,
        setupSidePanel: setupSidePanel,
        showRowMessage: showRowMessage,
        updateCardVisibility: updateCardVisibility,
        updateMappingFilter: updateMappingFilter,
        updateMappingRowVisibility: updateMappingRowVisibility,
        updateRowSearchData: updateRowSearchData,
        updateSidePanel: updateSidePanel,
        toggleVisibility: toggleVisibility
    };
});
