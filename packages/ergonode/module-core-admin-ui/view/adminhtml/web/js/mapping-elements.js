define([
    'Ergonode_CoreAdminUi/js/text',
    'mage/translate'
], function (text, $t) {
    'use strict';

    function sourceLabel(source) {
        return source === 'magento' ? 'Magento' : 'Ergonode';
    }

    function typeBadgeHtml(type, role) {
        var className = 'vea-type-' + text.normalize(type).replace(/[^a-z0-9_-]/g, '-');

        return [
            '<span class="vea-type-badge ',
            text.escapeHtml(className),
            '"',
            role ? ' data-role="' + text.escapeHtml(role) + '"' : '',
            '>',
            text.escapeHtml(type),
            '</span>'
        ].join('');
    }

    function pendingBadgeHtml(label, badgeText) {
        label = label || $t('Element zostanie utworzony po zapisaniu');
        badgeText = badgeText || $t('DO UTWORZENIA');

        return [
            '<span class="veui-pending-badge" data-role="pending-badge" title="',
            text.escapeHtml(label),
            '" aria-label="',
            text.escapeHtml(label),
            '">',
            text.escapeHtml(badgeText),
            '</span>'
        ].join('');
    }

    function markNewMapping(row, side) {
        var slot;
        var target;

        side = side === 'ergo' ? 'ergo' : 'magento';
        slot = row ? row.querySelector('[data-role="pair-slot"][data-side="' + side + '"]') : null;
        target = slot ? slot.querySelector('.vea-card-subline') || slot : null;

        if (!row || !target) {
            return;
        }

        row.setAttribute('data-new-mapping', '1');
        slot.setAttribute('data-new-mapping-element', '1');
        if (!slot.querySelector('[data-role="new-mapping-badge"]')) {
            target.insertAdjacentHTML(
                'beforeend',
                pendingBadgeHtml(
                    $t('Nowe mapowanie zostanie zapisane po zatwierdzeniu zmian'),
                    $t('DO ZAPISU')
                ).replace('data-role="pending-badge"', 'data-role="new-mapping-badge"')
            );
        }
    }

    function metaHtml(payload) {
        return [
            '<span class="vea-card-subline">',
            '<code>',
            text.escapeHtml(payload.code),
            '</code>',
            typeBadgeHtml(payload.type),
            payload.scope ? '<span class="vea-scope">' + text.escapeHtml(payload.scope) + '</span>' : '',
            payload.pending_create ? pendingBadgeHtml() : '',
            '</span>'
        ].join('');
    }

    function searchText(payload) {
        return [payload.label || '', payload.code || '', payload.scope || '', payload.type || ''].join(' ');
    }

    function payloadFrom(element, source) {
        if (!element || !element.getAttribute('data-code')) {
            return null;
        }

        return {
            label: element.getAttribute('data-label') || '',
            code: element.getAttribute('data-code') || '',
            type: element.getAttribute('data-type') || '',
            scope: element.getAttribute('data-scope') || '',
            source: source,
            pending_create: element.getAttribute('data-pending-create') === '1',
            create_label: element.getAttribute('data-create-label') || ''
        };
    }

    return {
        sourceLabel: sourceLabel,
        typeBadgeHtml: typeBadgeHtml,
        pendingBadgeHtml: pendingBadgeHtml,
        markNewMapping: markNewMapping,
        metaHtml: metaHtml,
        searchText: searchText,
        slotPayload: function (slot) {
            var source = slot && slot.getAttribute('data-side') === 'magento' ? 'magento' : 'ergo';

            return payloadFrom(slot, source);
        },
        cardPayload: function (card) {
            return payloadFrom(card, card ? card.getAttribute('data-source') || '' : '');
        },
        isActionTarget: function (target) {
            return !!(target && target.closest(
                '[data-role="entity-options"], a, button, input, label, select, summary, textarea'
            ));
        }
    };
});
