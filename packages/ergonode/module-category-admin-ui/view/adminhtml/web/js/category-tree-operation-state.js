define(['mage/translate'], function ($t) {
    'use strict';

    function create(root) {
        var document = root.ownerDocument;
        var originalTabIndex = root.getAttribute('tabindex');
        var status = document.createElement('div');
        var spinner = document.createElement('span');
        var card = document.createElement('div');
        var triggerSelector;
        var label = document.createElement('span');
        var regions = [];
        var busy = false;

        status.className = 'vec-operation-status';
        status.dataset.role = 'category-operation-status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        status.tabIndex = -1;
        status.hidden = true;
        spinner.className = 'vec-operation-spinner';
        spinner.setAttribute('aria-hidden', 'true');
        card.className = 'vec-operation-card';
        card.append(spinner, label);
        status.append(card);
        root.insertBefore(status, root.querySelector('.vec-layout'));

        function finish() {
            var restoreFocus = document.activeElement === status;
            var trigger;

            regions.forEach(function (region) {
                region.element.inert = region.inert;
                if (region.ariaBusy === null) {
                    region.element.removeAttribute('aria-busy');
                } else {
                    region.element.setAttribute('aria-busy', region.ariaBusy);
                }
            });
            regions = [];
            busy = false;
            status.hidden = true;
            if (restoreFocus) {
                trigger = root.querySelector(triggerSelector);
                if (trigger && !trigger.disabled) {
                    trigger.focus({preventScroll: true});
                } else {
                    root.tabIndex = -1;
                    root.focus({preventScroll: true});
                }
            }
        }

        return {
            start: function (operation) {
                if (busy) {
                    return false;
                }
                busy = true;
                status.classList.toggle('is-saving', operation === 'save');
                label.textContent = operation === 'save' ? $t('Saving category mappings…') : $t('Connecting categories…');
                triggerSelector = operation === 'save' ? '[data-role="save-categories"]'
                    : '[data-bulk-options-source="magento"] > summary';
                regions = Array.from(root.querySelectorAll('.vec-toolbar, .vec-layout')).map(function (element) {
                    var region = {element: element, inert: element.inert, ariaBusy: element.getAttribute('aria-busy')};

                    element.inert = true;
                    element.setAttribute('aria-busy', 'true');
                    return region;
                });
                status.hidden = false;
                status.focus({preventScroll: true});
                return true;
            },
            finish: finish,
            destroy: function () {
                finish();
                status.remove();
                if (originalTabIndex === null) {
                    root.removeAttribute('tabindex');
                } else {
                    root.setAttribute('tabindex', originalTabIndex);
                }
            }
        };
    }

    return {create: create};
});
