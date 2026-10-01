define([
    'Ergonode_CoreAdminUi/js/workspace',
    'Ergonode_CoreAdminUi/js/text',
    'Ergonode_CoreAdminUi/js/workspace-context',
    'Ergonode_CoreAdminUi/js/request',
    'Ergonode_CoreAdminUi/js/buttons',
    'Ergonode_CoreAdminUi/js/visibility-toggle',
    'Ergonode_CoreAdminUi/js/source-bulk-transfer',
    'Ergonode_CoreAdminUi/js/entity-options',
    'Ergonode_LanguageAdminUi/js/language-source-options',
    'Ergonode_LanguageAdminUi/js/language-autosave',
    'Ergonode_CoreAdminUi/js/mapping-requirements',
    'Ergonode_CoreAdminUi/js/snapshot-removal',
    'mage/translate'
], function (workspace, text, workspaceContext, request, buttons, visibilityToggle, sourceBulkTransfer, entityOptions, languageSourceOptions, languageAutosave, mappingRequirements, snapshotRemoval, $t) {
    'use strict';

    var dragMimeType = 'application/vnd.ergonode.language+json';
    var languageActivatedEvent = 'ergonode:language-activated';

    function setupErgonodeEntityOptions(root) {
        root.querySelectorAll(
            '[data-role="source-panel"][data-source-panel="ergo"] [data-role="entity-card"]'
        ).forEach(function (card) {
            var toggle = card.querySelector('[data-role="source-active-toggle"]');
            var placeholder = card.querySelector('[data-role="entity-options-placeholder"]');
            var label = card.getAttribute('data-label') || card.getAttribute('data-code') || '';
            var active = isCardActive(card);
            var menu;

            menu = snapshotRemoval.enhance(card, {
                active: active,
                mappingAction: true,
                snapshot: {
                    code: card.getAttribute('data-code') || '',
                    label: label,
                    actionLabel: $t('Remove from list'),
                    title: $t('Remove from list'),
                    ariaLabel: $t('Remove from list')
                },
                menuLabel: $t('Opcje języka') + ': ' + label,
                toggle: toggle
            });
            if (menu && placeholder) {
                placeholder.remove();
            }
        });
    }

    function cardPayload(card) {
        return {
            source: card.getAttribute('data-source') || '',
            code: card.getAttribute('data-code') || '',
            label: card.getAttribute('data-label') || '',
            store_code: card.getAttribute('data-store-code') || '',
            locale: card.getAttribute('data-locale') || ''
        };
    }

    function findCard(root, source, code) {
        var match = null;

        root.querySelectorAll('[data-role="entity-card"][data-source="' + source + '"]').forEach(function (card) {
            if (!match && card.getAttribute('data-code') === String(code)) {
                match = card;
            }
        });

        return match;
    }

    function unlinkMappingHtml() {
        return [
            '<button type="button" class="vea-link-indicator vel-unlink-mapping" ',
            'data-role="unlink-mapping" title="',
            text.escapeHtml($t('Unlink mapping')),
            '" aria-label="',
            text.escapeHtml($t('Unlink mapping')),
            '"><span aria-hidden="true"></span></button>'
        ].join('');
    }

    function emptySlotHtml(side) {
        return [
            '<strong>',
            text.escapeHtml(side === 'ergo' ? $t('Upuść język') : $t('Upuść Store View')),
            '</strong><span data-role="slot-hint">',
            text.escapeHtml(side === 'ergo' ? $t('z Ergonode') : $t('z Magento')),
            '</span>'
        ].join('');
    }

    function filledSlotHtml(payload) {
        var meta = ['<span class="vea-card-subline"><code>'];

        meta.push(text.escapeHtml(payload.source === 'magento' ? payload.store_code : payload.code), '</code>');
        if (payload.source === 'magento') {
            meta.push(
                '<span class="vea-type-badge vea-type-store-view">',
                text.escapeHtml(payload.locale),
                '</span>'
            );
        }
        meta.push('</span>');

        return [
            '<strong>',
            text.escapeHtml(payload.label),
            '</strong>',
            meta.join('')
        ].join('');
    }

    function mappingRowHtml(language, store) {
        return [
            '<div class="vea-pair-card" data-role="pair-slot" data-side="ergo" data-code="',
            text.escapeHtml(language.code),
            '" data-label="',
            text.escapeHtml(language.label),
            '">',
            filledSlotHtml(language),
            '</div>',
            unlinkMappingHtml(),
            '<div class="vea-pair-card" data-role="pair-slot" data-side="magento" data-code="',
            text.escapeHtml(store.code),
            '" data-label="',
            text.escapeHtml(store.label),
            '" data-store-code="',
            text.escapeHtml(store.store_code),
            '" data-locale="',
            text.escapeHtml(store.locale),
            '">',
            filledSlotHtml(store),
            '</div>'
        ].join('');
    }

    function slotPayload(slot) {
        var source = slot ? slot.getAttribute('data-side') || '' : '';
        var code = slot ? slot.getAttribute('data-code') || '' : '';

        if (!source || !code) {
            return null;
        }

        return {
            source: source,
            code: code,
            label: slot.getAttribute('data-label') || '',
            store_code: slot.getAttribute('data-store-code') || '',
            locale: slot.getAttribute('data-locale') || ''
        };
    }

    function setSlotFilled(slot, payload) {
        if (!slot || !payload) {
            return;
        }

        slot.classList.remove('is-empty', 'is-drop-ready');
        slot.setAttribute('data-code', payload.code || '');
        slot.setAttribute('data-label', payload.label || '');
        slot.setAttribute('data-store-code', payload.store_code || '');
        slot.setAttribute('data-locale', payload.locale || '');
        slot.innerHTML = filledSlotHtml(payload);
    }

    function serializeMappings(root) {
        var mappings = [];

        root.querySelectorAll('[data-role="mapping-row"]').forEach(function (row) {
            var left = row.querySelector('[data-role="pair-slot"][data-side="ergo"]');
            var right = row.querySelector('[data-role="pair-slot"][data-side="magento"]');

            if (left && right && (left.getAttribute('data-code') || right.getAttribute('data-code'))) {
                mappings.push({
                    left: left.getAttribute('data-code') ? {code: left.getAttribute('data-code')} : null,
                    right: right.getAttribute('data-code') ? {code: right.getAttribute('data-code')} : null
                });
            }
        });

        return mappings;
    }

    function serializeVisibility(root) {
        var visibility = [];

        root.querySelectorAll('[data-role="entity-card"]').forEach(function (card) {
            var activeToggle = card.querySelector('[data-role="source-active-toggle"]');

            visibility.push({
                source: card.getAttribute('data-source') || '',
                code: card.getAttribute('data-code') || '',
                active: activeToggle ? buttons.isPressed(activeToggle) : true
            });
        });

        return visibility;
    }

    function serializeSnapshot(root) {
        return {
            mappings: serializeMappings(root),
            visibility: serializeVisibility(root)
        };
    }

    function isCardActive(card) {
        var activeToggle = card ? card.querySelector('[data-role="source-active-toggle"]') : null;

        return activeToggle ? buttons.isPressed(activeToggle) : true;
    }

    function showsOmittedCards(panel) {
        var toggle = panel ? panel.querySelector('[data-role="visibility-toggle"]') : null;

        return visibilityToggle.isVisible(toggle);
    }

    function findAutoMatches(root) {
        var matches = [];
        var exact = new Map();
        var prefixes = new Map();

        root.querySelectorAll('[data-role="entity-card"][data-source="ergo"]').forEach(function (candidate) {
            var code;
            var prefix;

            if (!isCardActive(candidate)) {
                return;
            }
            code = text.normalize(candidate.getAttribute('data-code') || '').replace('-', '_');
            prefix = code.split('_')[0];
            if (!exact.has(code)) {
                exact.set(code, candidate);
            }
            if (!prefixes.has(prefix)) {
                prefixes.set(prefix, candidate);
            }
        });
        root.querySelectorAll(
            '[data-role="entity-card"][data-source="magento"]:not(.is-mapped)'
        ).forEach(function (storeCard) {
            var locale = text.normalize(storeCard.getAttribute('data-locale') || '').replace('-', '_');
            var languageCard;

            if (!isCardActive(storeCard)) {
                return;
            }
            languageCard = exact.get(locale) || prefixes.get(locale.split('_')[0]);
            if (languageCard) {
                matches.push({
                    language: cardPayload(languageCard),
                    store: cardPayload(storeCard),
                    languageCard: languageCard,
                    storeCard: storeCard
                });
            }
        });

        return matches;
    }

    function oppositeSource(source) {
        return source === 'ergo' ? 'magento' : 'ergo';
    }

    function findMatchingDraft(root, payload) {
        var match = null;
        var opposite = oppositeSource(payload.source);

        root.querySelectorAll('[data-role="mapping-row"]').forEach(function (row) {
            var ownSlot = row.querySelector(
                '[data-role="pair-slot"][data-side="' + payload.source + '"]'
            );
            var oppositeSlot = row.querySelector(
                '[data-role="pair-slot"][data-side="' + opposite + '"]'
            );

            if (!match && ownSlot && oppositeSlot
                && ownSlot.getAttribute('data-code') === payload.code
                && !slotPayload(oppositeSlot)) {
                match = row;
            }
        });

        return match;
    }

    function findComplementaryDraft(root, source) {
        var matches = [];
        var opposite = oppositeSource(source);

        root.querySelectorAll('[data-role="mapping-row"]').forEach(function (row) {
            var ownSlot = row.querySelector(
                '[data-role="pair-slot"][data-side="' + source + '"]'
            );
            var oppositeSlot = row.querySelector(
                '[data-role="pair-slot"][data-side="' + opposite + '"]'
            );

            if (ownSlot && oppositeSlot
                && !slotPayload(ownSlot)
                && slotPayload(oppositeSlot)) {
                matches.push(row);
            }
        });

        return matches.length === 1 ? matches[0] : null;
    }

    function findManualPair(root, draggedCard, targetCard) {
        var languageCard;
        var storeCard;
        var storePayload;

        if (!draggedCard || !targetCard
            || draggedCard === targetCard
            || draggedCard.getAttribute('data-source') === targetCard.getAttribute('data-source')
            || !isCardActive(draggedCard)
            || !isCardActive(targetCard)) {
            return null;
        }
        languageCard = draggedCard.getAttribute('data-source') === 'ergo' ? draggedCard : targetCard;
        storeCard = draggedCard.getAttribute('data-source') === 'magento' ? draggedCard : targetCard;
        storePayload = cardPayload(storeCard);
        if (languageCard.getAttribute('data-source') !== 'ergo'
            || storeCard.getAttribute('data-source') !== 'magento'
            || (storeCard.classList.contains('is-mapped')
                && !findMatchingDraft(root, storePayload))) {
            return null;
        }

        return {
            language: cardPayload(languageCard),
            store: cardPayload(storeCard)
        };
    }

    function updateSourcePanel(root, panel) {
        var queryInput = panel ? panel.querySelector('[data-role="source-search"]') : null;
        var query = text.normalize(queryInput ? queryInput.value : '');
        var showOmitted = showsOmittedCards(panel);

        if (!panel) {
            return;
        }

        panel.querySelectorAll('[data-role="entity-card"]').forEach(function (card) {
            var active = isCardActive(card);
            var matchesQuery = text.normalize(card.getAttribute('data-search') || '').indexOf(query) !== -1;
            var visible = matchesQuery && (active || showOmitted);

            card.hidden = !visible;
            card.classList.toggle('is-filter-hidden', !visible);
            card.classList.toggle('is-inactive', !active);
        });
    }

    function updateSourcePanels(root) {
        root.querySelectorAll('[data-role="source-panel"]').forEach(function (panel) {
            updateSourcePanel(root, panel);
        });
    }

    function updateMappingAvailability(root) {
        var active = {ergo: new Set(), magento: new Set()};

        root.querySelectorAll('[data-role="entity-card"]').forEach(function (card) {
            var source = card.getAttribute('data-source');

            if (active[source] && isCardActive(card)) {
                active[source].add(card.getAttribute('data-code'));
            }
        });
        root.querySelectorAll('[data-role="mapping-row"]').forEach(function (row) {
            var left = row.querySelector('[data-role="pair-slot"][data-side="ergo"]');
            var right = row.querySelector('[data-role="pair-slot"][data-side="magento"]');
            var language = left && left.getAttribute('data-code');
            var store = right && right.getAttribute('data-code');
            var available = active.ergo.has(language) && active.magento.has(store);
            var badge = row.querySelector('[data-role="mapping-inactive"]');

            row.setAttribute('data-mapping-active', available ? 'true' : 'false');
            row.classList.toggle('vea-status-tone-ok', available);
            row.classList.toggle('vea-status-tone-warning', !available);
            if (language && store && !available) {
                if (!badge) {
                    (left.querySelector('.vea-card-subline') || left).insertAdjacentHTML(
                        'beforeend',
                        '<span class="veui-pending-badge" data-role="mapping-inactive">' +
                        text.escapeHtml($t('Mapping inactive: language or Store View unavailable.')) + '</span>'
                    );
                }
            } else if (badge) {
                badge.remove();
            }
        });
    }

    function updateState(root, dirty, requirements) {
        var count = root.querySelector('[data-role="mapping-count"]');
        var storeCount = root.querySelector('[data-role="store-mapping-count"]');
        var empty = root.querySelector('[data-role="mapping-empty"]');
        var total = root.querySelectorAll('[data-role="mapping-row"]').length;
        var mappedStores = root.querySelectorAll(
            '[data-role="entity-card"][data-source="magento"].is-mapped'
        ).length;
        var stores = root.querySelectorAll(
            '[data-role="entity-card"][data-source="magento"]'
        ).length;

        if (count) {
            count.textContent = String(total);
        }
        if (storeCount) {
            storeCount.textContent = String(mappedStores) + ' / ' + String(stores);
        }
        if (empty) {
            empty.hidden = total > 0;
        }
        updateMappingAvailability(root);
        if (requirements) {
            requirements.refresh();
        }
        languageSourceOptions.sync(root, findAutoMatches(root).length);
    }

    function markMappingsPersisted(root) {
        root.querySelectorAll('[data-new-mapping="1"]').forEach(function (row) {
            var badge = row.querySelector('[data-role="new-mapping-badge"]');

            row.removeAttribute('data-new-mapping');
            row.querySelectorAll('[data-new-mapping-element="1"]').forEach(function (slot) {
                slot.removeAttribute('data-new-mapping-element');
            });
            if (badge) {
                badge.remove();
            }
        });
    }

    function initialize(root, config) {
        return workspace.mount(root, function (scope) {
            var canSave = config.canSave === true;
            var reloading = false;
            var draggedCard = null;
            var dragPreview = null;
            var dragFrame = null;
            var mappingList = root.querySelector('[data-role="mapping-list"]');
            var context = workspaceContext.create(scope, root, {
                publish: false,
                serialize: function () {
                    return serializeSnapshot(root);
                }
            });
            var message = context.message;
            var dirty = context.dirty;
            var requirements = mappingRequirements.create(root, {
                rowSelector: '[data-role="mapping-row"][data-mapping-active="true"]'
            });
            var autosave = languageAutosave.create(root, config, {
                serialize: function () {
                    return serializeSnapshot(root);
                },
                onError: function (error) {
                    message.show(
                        'error',
                        error.message || $t('Unable to save language mappings. Check the Magento logs for details.')
                    );
                },
                onSaved: function () {
                    markMappingsPersisted(root);
                    dirty.capture();
                    updateState(root, dirty, requirements);
                }
            });

            function lockForReload() {
                if (reloading) {
                    return false;
                }
                reloading = true;
                root.setAttribute('inert', '');
                return true;
            }

            function unlockAfterFailure() {
                reloading = false;
                root.removeAttribute('inert');
            }

            function clearDragFeedback() {
                if (dragFrame !== null) {
                    window.cancelAnimationFrame(dragFrame);
                    dragFrame = null;
                }
                if (draggedCard) {
                    draggedCard.classList.remove('is-dragging');
                }
                if (dragPreview) {
                    dragPreview.remove();
                    dragPreview = null;
                }
            }

            function setCardDragImage(event, card) {
                var bounds = card.getBoundingClientRect();
                var preview = card.cloneNode(true);
                var offsetX = event.clientX - bounds.left;
                var offsetY = event.clientY - bounds.top;

                preview.removeAttribute('data-role');
                preview.removeAttribute('draggable');
                preview.removeAttribute('role');
                preview.removeAttribute('tabindex');
                preview.removeAttribute('aria-label');
                preview.classList.remove('is-mapped', 'is-drop-ready', 'is-dragging');
                preview.classList.add('vel-source-drag-preview');
                preview.setAttribute('aria-hidden', 'true');
                preview.setAttribute('inert', '');
                preview.querySelectorAll('button, details, [role="tooltip"]').forEach(function (element) {
                    element.remove();
                });
                preview.querySelectorAll('[id]').forEach(function (element) {
                    element.removeAttribute('id');
                });
                preview.style.left = String(bounds.left) + 'px';
                preview.style.top = String(bounds.top) + 'px';
                preview.style.width = String(bounds.width) + 'px';
                document.body.appendChild(preview);
                dragPreview = preview;

                try {
                    event.dataTransfer.setDragImage(
                        preview,
                        offsetX > 0 && offsetX < bounds.width ? offsetX : bounds.width / 2,
                        offsetY > 0 && offsetY < bounds.height ? offsetY : bounds.height / 2
                    );
                } catch (error) {
                    preview.remove();
                    dragPreview = null;
                }
                dragFrame = window.requestAnimationFrame(function () {
                    dragFrame = null;
                    if (draggedCard !== card) {
                        return;
                    }
                    card.classList.add('is-dragging');
                    if (dragPreview === preview) {
                        preview.style.left = '-10000px';
                        preview.style.top = '-10000px';
                        preview.classList.add('is-captured');
                    }
                });
            }

            languageSourceOptions.initialize(root);
            visibilityToggle.initialize(root);
            entityOptions.bind(scope, root);
            if (canSave) {
                setupErgonodeEntityOptions(root);
                snapshotRemoval.bind(scope, root, config, {
                    isDirty: dirty.isDirty.bind(dirty),
                    message: message,
                    beforeRemove: lockForReload,
                    onError: unlockAfterFailure
                });
            }

            function setStoreMapped(storeCode, mapped, sourceCard) {
                var card = sourceCard || findCard(root, 'magento', storeCode);

                if (!card) {
                    return;
                }
                card.classList.toggle('is-mapped', mapped);
                if (mapped) {
                    card.setAttribute('aria-disabled', 'true');
                } else {
                    card.removeAttribute('aria-disabled');
                }
            }

            function appendNewBadge(row, side) {
                var slot = row.querySelector('[data-role="pair-slot"][data-side="' + side + '"]');
                var target = slot ? slot.querySelector('.vea-card-subline') || slot : null;

                if (!target) {
                    return;
                }

                row.setAttribute('data-new-mapping', '1');
                slot.setAttribute('data-new-mapping-element', '1');
                target.insertAdjacentHTML(
                    'beforeend',
                    '<span class="veui-pending-badge" data-role="new-mapping-badge" title="' +
                    text.escapeHtml($t('Mapowanie oczekuje na zapis')) +
                    '" aria-label="' + text.escapeHtml($t('Mapowanie oczekuje na zapis')) +
                    '">' + text.escapeHtml($t('Zapisywanie')) + '</span>'
                );
            }

            function createEmptyMappingRow() {
                var row = document.createElement('article');

                row.className = 'vea-pair-row vea-status-tone-warning vel-pair-row';
                row.setAttribute('data-role', 'mapping-row');
                row.innerHTML = [
                    '<div class="vea-pair-card is-empty" data-role="pair-slot" data-side="ergo" data-code="" data-label="">',
                    emptySlotHtml('ergo'),
                    '</div>',
                    unlinkMappingHtml(),
                    '<div class="vea-pair-card is-empty" data-role="pair-slot" data-side="magento" data-code="" data-label="">',
                    emptySlotHtml('magento'),
                    '</div>'
                ].join('');
                return row;
            }

            function fillDraft(row, payload) {
                var slot = row ? row.querySelector(
                    '[data-role="pair-slot"][data-side="' + payload.source + '"]'
                ) : null;

                if (!slot || slotPayload(slot)) {
                    return false;
                }

                setSlotFilled(slot, payload);
                appendNewBadge(row, payload.source);
                if (payload.source === 'magento') {
                    setStoreMapped(payload.code, true);
                }

                return true;
            }

            function addSourcePayloadToMapping(root, payload, batchCard) {
                var card = batchCard || findCard(root, payload.source, payload.code);
                var ownDraft;
                var complementaryDraft;
                var row;
                var slot;

                if (!mappingList || !card || !isCardActive(card)) {
                    message.show('warning', $t('Ten element jest wyłączony z mapowania.'));
                    return false;
                }

                ownDraft = findMatchingDraft(root, payload);
                if (payload.source === 'magento'
                    && card.classList.contains('is-mapped')
                    && !ownDraft) {
                    message.show('warning', $t('Ten Store View ma już przypisany język.'));
                    return false;
                }

                complementaryDraft = findComplementaryDraft(root, payload.source);
                if (complementaryDraft && fillDraft(complementaryDraft, payload)) {
                    if (ownDraft && ownDraft !== complementaryDraft) {
                        ownDraft.remove();
                    }
                    if (!batchCard) {
                        updateState(root, dirty, requirements);
                    }

                    return true;
                }
                if (ownDraft) {
                    message.show('warning', $t('Ten element oczekuje już na połączenie.'));
                    return false;
                }

                row = createEmptyMappingRow();
                slot = row.querySelector('[data-role="pair-slot"][data-side="' + payload.source + '"]');
                setSlotFilled(slot, payload);
                appendNewBadge(row, payload.source);
                mappingList.insertBefore(row, mappingList.firstChild);
                if (payload.source === 'magento') {
                    setStoreMapped(payload.code, true, card);
                }
                if (!batchCard) {
                    updateState(root, dirty, requirements);
                }

                return true;
            }

            function addManualMapping(language, store) {
                var languageDraft = findMatchingDraft(root, language);
                var storeDraft = findMatchingDraft(root, store);
                var draft = languageDraft || storeDraft;
                var payload = languageDraft ? store : language;

                if (!draft) {
                    return addMapping(language, store);
                }

                if (!fillDraft(draft, payload)) {
                    return false;
                }
                if (languageDraft && storeDraft && languageDraft !== storeDraft) {
                    storeDraft.remove();
                }
                updateState(root, dirty, requirements);

                return true;
            }

            function addMapping(language, store, batchMatch) {
                var storeCard;
                var languageCard;

                if (!mappingList || language.source !== 'ergo' || store.source !== 'magento') {
                    return false;
                }
                storeCard = batchMatch ? batchMatch.storeCard : findCard(root, 'magento', store.code);
                languageCard = batchMatch ? batchMatch.languageCard : findCard(root, 'ergo', language.code);
                if (!storeCard || !languageCard || !isCardActive(storeCard) || !isCardActive(languageCard)
                    || storeCard.classList.contains('is-mapped')) {
                    message.show('warning', $t('Ten Store View ma już przypisany język.'));
                    return false;
                }

                var row = document.createElement('article');
                row.className = 'vea-pair-row vea-status-tone-ok vel-pair-row';
                row.setAttribute('data-role', 'mapping-row');
                row.innerHTML = mappingRowHtml(language, store);
                appendNewBadge(row, 'magento');
                mappingList.appendChild(row);
                setStoreMapped(store.code, true, storeCard);
                if (!batchMatch) {
                    updateState(root, dirty, requirements);
                }

                return true;
            }

            function removeMappingRow(row) {
                var store = row ? row.querySelector('[data-side="magento"]') : null;

                if (store) {
                    setStoreMapped(store.getAttribute('data-code') || '', false);
                }
                if (row) {
                    row.remove();
                }
            }

            function unlinkMappingRow(row) {
                if (!row) {
                    return false;
                }

                removeMappingRow(row);
                updateState(root, dirty, requirements);

                return true;
            }

            function removeMappingsForCard(card) {
                var payload = cardPayload(card);

                root.querySelectorAll('[data-role="mapping-row"]').forEach(function (row) {
                    var slot = row.querySelector(
                        '[data-role="pair-slot"][data-side="' + payload.source + '"]'
                    );

                    if (slot && slot.getAttribute('data-code') === payload.code) {
                        removeMappingRow(row);
                    }
                });
            }

            function countMappingsForCard(card) {
                var payload = cardPayload(card);
                var count = 0;

                root.querySelectorAll('[data-role="mapping-row"]').forEach(function (row) {
                    var slot = row.querySelector(
                        '[data-role="pair-slot"][data-side="' + payload.source + '"]'
                    );

                    if (slot && slot.getAttribute('data-code') === payload.code) {
                        count++;
                    }
                });

                return count;
            }

            function setCardActive(card, active) {
                var toggle = card ? card.querySelector('[data-role="source-active-toggle"]') : null;

                if (!card) {
                    return false;
                }

                if (!active && requirements.isRequired(card)) {
                    message.show(
                        'warning',
                        $t('Tego elementu nie można wyłączyć, ponieważ jest wymagany do synchronizacji.'),
                        3500
                    );
                    return false;
                }
                if (!toggle) {
                    return false;
                }
                if (!active && countMappingsForCard(card) > 1 && !window.confirm($t(
                    'Excluding this item will remove multiple mappings and save the change immediately. Continue?'
                ))) {
                    return false;
                }

                buttons.setPressed(toggle, active);
                snapshotRemoval.setActiveState(card, active);
                if (!active) {
                    removeMappingsForCard(card);
                }
                updateSourcePanels(root);
                updateState(root, dirty, requirements);

                return true;
            }

            root.querySelectorAll('[data-role="mapping-row"] [data-side="magento"]').forEach(function (slot) {
                setStoreMapped(slot.getAttribute('data-code') || '', true);
            });
            updateSourcePanels(root);
            if (canSave) {
                sourceBulkTransfer.bind(scope, root, {
                    transfer: function (card) {
                        return addSourcePayloadToMapping(root, cardPayload(card), card);
                    },
                    afterTransfer: function () {
                        updateState(root, dirty, requirements);
                        autosave.schedule();
                    }
                });

                scope.delegate('dblclick', '[data-role="entity-card"]', function (event, card) {
                    if (event.target.closest('[data-role="entity-options"], button, input, a, label')) {
                        return;
                    }

                    event.preventDefault();
                    if (addSourcePayloadToMapping(root, cardPayload(card))) {
                        autosave.schedule();
                    }
                });
                scope.delegate('keydown', '[data-role="entity-card"]', function (event, card) {
                    if ((event.key === 'Enter' || event.key === ' ')
                        && !event.target.closest('[data-role="entity-options"], button, input, label')) {
                        event.preventDefault();
                        card.dispatchEvent(new MouseEvent('dblclick', {bubbles: true}));
                    }
                });
                scope.delegate('click', '[data-role="entity-add-to-mapping"]', function (event, button) {
                    var card = button.closest('[data-role="entity-card"]');

                    event.preventDefault();
                    event.stopPropagation();
                    if (card && addSourcePayloadToMapping(root, cardPayload(card))) {
                        autosave.schedule();
                    }
                });
                scope.delegate('dragstart', '[data-role="entity-card"]', function (event, card) {
                    var payload = cardPayload(card);

                    if (!event.dataTransfer || !isCardActive(card)
                        || (card.getAttribute('data-source') === 'magento'
                            && card.classList.contains('is-mapped')
                            && !findMatchingDraft(root, payload))) {
                        event.preventDefault();
                        return;
                    }

                    clearDragFeedback();
                    draggedCard = card;
                    root.classList.add('is-dragging-language-source');
                    root.classList.add('is-dragging-' + card.getAttribute('data-source'));
                    event.dataTransfer.effectAllowed = 'linkMove';
                    event.dataTransfer.setData(dragMimeType, JSON.stringify(payload));
                    setCardDragImage(event, card);
                });
                scope.delegate('dragover', '[data-role="source-panel"] [data-role="entity-card"]', function (event, card) {
                    if (!findManualPair(root, draggedCard, card)) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();
                    card.classList.add('is-drop-ready');
                    if (event.dataTransfer) {
                        event.dataTransfer.dropEffect = 'link';
                    }
                });
                scope.delegate('dragleave', '[data-role="source-panel"] [data-role="entity-card"]', function (event, card) {
                    card.classList.remove('is-drop-ready');
                });
                scope.delegate('drop', '[data-role="source-panel"] [data-role="entity-card"]', function (event, card) {
                    var pair = findManualPair(root, draggedCard, card);

                    card.classList.remove('is-drop-ready');
                    if (!pair) {
                        return;
                    }
                    event.preventDefault();
                    event.stopPropagation();
                    if (addManualMapping(pair.language, pair.store)) {
                        autosave.schedule();
                    }
                });
                scope.delegate('dragend', '[data-role="entity-card"]', function () {
                    clearDragFeedback();
                    draggedCard = null;
                    root.classList.remove('is-dragging-language-source', 'is-dragging-ergo', 'is-dragging-magento');
                    root.querySelectorAll('[data-role="disable-drop-zone"]').forEach(function (zone) {
                        zone.classList.remove('is-drop-ready', 'is-drop-active');
                    });
                    root.querySelectorAll('[data-role="pair-slot"]').forEach(function (slot) {
                        slot.classList.remove('is-drop-ready');
                    });
                    root.querySelectorAll('[data-role="entity-card"].is-drop-ready').forEach(function (card) {
                        card.classList.remove('is-drop-ready');
                    });
                });
                scope.delegate('dragover', '[data-role="pair-slot"]', function (event, slot) {
                    if (!draggedCard || draggedCard.getAttribute('data-source') !== slot.getAttribute('data-side')) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();
                    slot.classList.add('is-drop-ready');
                    if (event.dataTransfer) {
                        event.dataTransfer.dropEffect = 'move';
                    }
                });
                scope.delegate('dragleave', '[data-role="pair-slot"]', function (event, slot) {
                    slot.classList.remove('is-drop-ready');
                });
                scope.delegate('drop', '[data-role="pair-slot"]', function (event, slot) {
                    var payload;
                    var previous;
                    var row;
                    var sourceDraft;

                    slot.classList.remove('is-drop-ready');
                    if (!draggedCard || draggedCard.getAttribute('data-source') !== slot.getAttribute('data-side')) {
                        return;
                    }
                    event.preventDefault();
                    event.stopPropagation();
                    payload = cardPayload(draggedCard);
                    previous = slotPayload(slot);
                    row = slot.closest('[data-role="mapping-row"]');
                    sourceDraft = findMatchingDraft(root, payload);
                    if (previous && previous.source === 'magento') {
                        setStoreMapped(previous.code, false);
                    }
                    setSlotFilled(slot, payload);
                    if (payload.source === 'magento') {
                        setStoreMapped(payload.code, true);
                    }
                    if (sourceDraft && sourceDraft !== row) {
                        sourceDraft.remove();
                    }
                    updateState(root, dirty, requirements);
                    autosave.schedule();
                });
                scope.delegate('dragover', '[data-role="mapping-panel"]', function (event) {
                    if (!draggedCard || event.target.closest('[data-role="pair-slot"]')) {
                        return;
                    }

                    event.preventDefault();
                    if (event.dataTransfer) {
                        event.dataTransfer.dropEffect = 'move';
                    }
                });
                scope.delegate('drop', '[data-role="mapping-panel"]', function (event) {
                    if (!draggedCard || event.target.closest('[data-role="pair-slot"]')) {
                        return;
                    }

                    event.preventDefault();
                    if (addSourcePayloadToMapping(root, cardPayload(draggedCard))) {
                        autosave.schedule();
                    }
                });
                scope.delegate('dragover', '[data-role="disable-drop-zone"]', function (event, zone) {
                    var panel = zone.closest('[data-role="source-panel"]');

                    if (!draggedCard || !panel
                        || draggedCard.getAttribute('data-source') !== panel.getAttribute('data-source-panel')) {
                        return;
                    }

                    event.preventDefault();
                    zone.classList.add('is-drop-ready');
                    if (event.dataTransfer) {
                        event.dataTransfer.dropEffect = 'move';
                    }
                });
                scope.delegate('dragleave', '[data-role="disable-drop-zone"]', function (event, zone) {
                    zone.classList.remove('is-drop-ready');
                });
                scope.delegate('drop', '[data-role="disable-drop-zone"]', function (event, zone) {
                    if (!draggedCard) {
                        return;
                    }

                    event.preventDefault();
                    zone.classList.remove('is-drop-ready');
                    zone.classList.add('is-drop-active');
                    if (zone.getAttribute('data-drop-action') === 'remove-from-list') {
                        snapshotRemoval.requestRemoval(draggedCard);
                        return;
                    }
                    if (setCardActive(draggedCard, false)) {
                        autosave.schedule();
                    }
                });
                scope.delegate('click', '[data-role="unlink-mapping"]', function (event, button) {
                    var row = button.closest('[data-role="mapping-row"]');

                    event.preventDefault();
                    event.stopPropagation();
                    if (unlinkMappingRow(row)) {
                        autosave.schedule();
                    }
                });
                scope.listen(root, languageActivatedEvent, function (event) {
                    var detail = event.detail || {};
                    var row = detail.row;
                    var language = detail.language || {};
                    var slot;

                    if (reloading || !row || !root.contains(row) || !language.code) {
                        return;
                    }

                    slot = row.querySelector('[data-role="pair-slot"][data-side="ergo"]');
                    if (!slot || slotPayload(slot)) {
                        return;
                    }

                    setSlotFilled(slot, {
                        source: 'ergo',
                        code: language.code,
                        label: language.label || language.code,
                        store_code: '',
                        locale: ''
                    });
                    appendNewBadge(row, 'ergo');
                    updateState(root, dirty, requirements);
                    autosave.schedule();
                });
            }
            scope.delegate('input', '[data-role="source-search"]', function (event, input) {
                var panel = input.closest('[data-role="source-panel"]');

                if (!panel) {
                    return;
                }
                updateSourcePanel(root, panel);
            });
            if (canSave) {
                scope.delegate('click', '[data-role="source-active-toggle"]', function (event, button) {
                    if (setCardActive(
                        button.closest('[data-role="entity-card"]'),
                        !buttons.isPressed(button)
                    )) {
                        autosave.schedule();
                    }
                });
            }
            scope.delegate('click', '[data-role="visibility-toggle"]', function (event, button) {
                visibilityToggle.toggle(button);
                updateSourcePanels(root);
            });
            if (canSave) {
                scope.delegate('click', '[data-role="auto-match"]', function () {
                    var matched = 0;

                    findAutoMatches(root).forEach(function (match) {
                        if (addMapping(match.language, match.store, match)) {
                            matched++;
                        }
                    });

                    message.show(
                        matched > 0 ? 'success' : 'warning',
                        matched > 0
                            ? $t('Dodano mapowania: %1.').replace('%1', matched)
                            : $t('Nie znaleziono nowych zgodnych locale.'),
                        3500
                    );
                    if (matched > 0) {
                        updateState(root, dirty, requirements);
                        autosave.schedule();
                    }
                });
            }
            scope.delegate('click', '[data-role="refresh-ergonode"]', function (event, button) {
                var panel = button.closest('[data-role="source-panel"]');

                if (button.disabled || !lockForReload()) {
                    return;
                }
                if (panel) {
                    panel.classList.add('is-refreshing');
                }
                buttons.run(button, function () {
                    return autosave.flush().then(function () {
                        return request.post(config.urls && config.urls.refresh, config, {});
                    });
                }).then(function () {
                    window.location.reload();
                }).catch(function (error) {
                    unlockAfterFailure();
                    if (panel) {
                        panel.classList.remove('is-refreshing');
                    }
                    if (autosave.hasError()) {
                        return;
                    }
                    message.error(
                        $t('Nie udało się odświeżyć języków'),
                        error,
                        $t('Nie udało się wczytać języków z Ergonode. Szczegóły znajdziesz w logach Magento.'),
                        {
                            label: $t('Przejdź do konfiguracji'),
                            url: config.urls && config.urls.configuration
                        }
                    );
                });
            });
            if (canSave) {
                scope.delegate('click', '[data-role="retry-autosave"]', function () {
                    autosave.retry();
                });
            }
            scope.cleanup(function () {
                clearDragFeedback();
                draggedCard = null;
            });

            dirty.capture();
            updateState(root, dirty, requirements);
        });
    }

    return function (config, element) {
        return initialize(element, config || {});
    };
});
