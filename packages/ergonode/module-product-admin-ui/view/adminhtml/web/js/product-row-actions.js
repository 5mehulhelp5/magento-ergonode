define(['mage/translate'], function ($t) {
    'use strict';

    function iconLabel(control, iconClass, label) {
        var icon = document.createElement('span');
        icon.className = iconClass || 'veui-split-button-check';
        icon.setAttribute('aria-hidden', 'true');
        control.append(icon, document.createTextNode(label));
        return control;
    }

    return function (actions, item, start) {
        var split = document.createElement('div');
        var details = document.createElement('details');
        var summary = document.createElement('summary');
        var menu = document.createElement('div');
        var primary = actions.find(function (action) { return action.primary; }) || actions[0];
        var focusOnOpen = false;

        function close() {
            if (menu.matches(':popover-open')) { menu.hidePopover(); }
            details.open = false;
        }
        function actionButton(action, className) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = className;
            button.dataset.productAction = action.code;
            button.dataset.productId = item.product_id;
            button.setAttribute('aria-label', $t(action.label) + ': ' + item.sku);
            button.title = action.requires_mapping && !item.ergonode_sku
                ? $t('This product has no Ergonode SKU mapping.') : $t(action.label);
            button.addEventListener('click', function () { close(); start(action); });
            return iconLabel(button, action.icon_class, $t(action.button_label || action.label));
        }
        function viewLink(className) {
            var view = document.createElement('a');
            view.href = item.product_url;
            view.target = '_blank';
            view.rel = 'noopener noreferrer';
            view.className = className;
            view.title = $t('View product');
            view.setAttribute('aria-label', $t('View product %1 (new tab)').replace('%1', item.sku));
            view.addEventListener('click', close);
            return iconLabel(view, 'vepp-view-icon', $t('View'));
        }
        if (!primary) { return viewLink('veui-button veui-button-toolbar'); }

        split.className = 'veui-split-button veui-section-navigation-active vepp-action-split';
        split.setAttribute('role', 'group');
        split.setAttribute('aria-label', $t('Product actions %1').replace('%1', item.sku));
        split.appendChild(actionButton(primary, 'veui-button veui-button-toolbar veui-split-button-main'));
        details.className = 'veui-split-button-options';
        summary.className = 'veui-split-button-toggle';
        summary.setAttribute('role', 'button');
        summary.setAttribute('aria-label', $t('Product actions %1').replace('%1', item.sku));
        summary.setAttribute('aria-expanded', 'false');
        summary.tabIndex = 0;
        iconLabel(summary, 'veui-split-button-toggle-icon', '');
        menu.className = 'veui-split-button-menu vepp-action-menu';
        menu.popover = 'auto';
        menu.id = 'vepp-actions-' + item.product_id;
        summary.setAttribute('aria-controls', menu.id);
        menu.setAttribute('role', 'group');
        menu.setAttribute('aria-label', $t('Product options %1').replace('%1', item.sku));
        [primary].concat(actions.filter(function (action) { return action !== primary; })).forEach(function (action) {
            menu.appendChild(actionButton(action, 'veui-split-button-option'));
        });
        menu.appendChild(viewLink('veui-split-button-option'));
        details.append(summary, menu);
        split.appendChild(details);

        details.addEventListener('toggle', function () {
            if (!menu.isConnected) { return; }
            summary.setAttribute('aria-expanded', String(details.open));
            if (!details.open) { menu.hidePopover(); return; }
            menu.showPopover();
            var anchor = split.getBoundingClientRect();
            var bounds = menu.getBoundingClientRect();
            menu.style.left = Math.max(8, Math.min(anchor.right - bounds.width, window.innerWidth - bounds.width - 8)) + 'px';
            menu.style.top = (anchor.bottom + bounds.height + 6 <= window.innerHeight
                ? anchor.bottom + 6 : Math.max(8, anchor.top - bounds.height - 6)) + 'px';
            if (focusOnOpen) { menu.querySelector('button:not(:disabled), a').focus(); focusOnOpen = false; }
        });
        menu.addEventListener('toggle', function () {
            if (!menu.matches(':popover-open')) { details.open = false; }
        });
        summary.addEventListener('keydown', function (event) {
            if (summary.getAttribute('aria-disabled') === 'true') { event.preventDefault(); return; }
            if (event.key === 'ArrowDown') {
                event.preventDefault(); focusOnOpen = true; details.open = true;
            }
        });
        summary.addEventListener('click', function (event) {
            if (summary.getAttribute('aria-disabled') === 'true') { event.preventDefault(); }
        });
        details.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { event.preventDefault(); close(); summary.focus(); }
        });
        menu.addEventListener('keydown', function (event) {
            var choices = Array.from(menu.querySelectorAll('button:not(:disabled), a'));
            var index = choices.indexOf(document.activeElement);
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                choices[(index + (event.key === 'ArrowDown' ? 1 : choices.length - 1)) % choices.length].focus();
            }
        });
        return split;
    };
});
