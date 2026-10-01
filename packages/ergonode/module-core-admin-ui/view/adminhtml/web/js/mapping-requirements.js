define([], function () {
    'use strict';

    var defaultOptions = {
        requiredCardSelector: '[data-mapping-required="true"]',
        rowSelector: '[data-role="mapping-row"]',
        slotSelector: '[data-role="pair-slot"]'
    };

    function optionsWithDefaults(options) {
        return Object.assign({}, defaultOptions, options || {});
    }

    function isRequired(card) {
        return !!card && card.getAttribute('data-mapping-required') === 'true';
    }

    function isCompleteRowForCard(row, card, options) {
        var source = card.getAttribute('data-source') || '';
        var code = card.getAttribute('data-code') || '';
        var slots = Array.prototype.slice.call(row.querySelectorAll(options.slotSelector));
        var requiredSlot = slots.some(function (slot) {
            return slot.getAttribute('data-side') === source
                && slot.getAttribute('data-code') === code;
        });

        return requiredSlot && slots.length > 1 && slots.every(function (slot) {
            return !!slot.getAttribute('data-code');
        });
    }

    function isMapped(root, card, options) {
        return Array.prototype.some.call(root.querySelectorAll(options.rowSelector), function (row) {
            return isCompleteRowForCard(row, card, options);
        });
    }

    function create(root, suppliedOptions) {
        var options = optionsWithDefaults(suppliedOptions);

        function getMissing() {
            return Array.prototype.filter.call(
                root.querySelectorAll(options.requiredCardSelector),
                function (card) {
                    return !isMapped(root, card, options);
                }
            );
        }

        function refresh() {
            var missing = getMissing();
            var missingSet = new Set(missing);

            root.querySelectorAll(options.requiredCardSelector).forEach(function (card) {
                var requirementMissing = missingSet.has(card);

                card.classList.toggle('is-requirement-missing', requirementMissing);
                card.classList.toggle('is-requirement-satisfied', !requirementMissing);
                card.setAttribute('aria-invalid', requirementMissing ? 'true' : 'false');
            });
            root.classList.toggle('has-missing-mapping-requirements', missing.length > 0);

            return missing;
        }

        return {
            isRequired: isRequired,
            refresh: refresh
        };
    }

    return {
        create: create,
        isRequired: isRequired
    };
});
