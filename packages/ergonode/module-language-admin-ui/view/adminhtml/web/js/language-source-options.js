define([
    'Ergonode_CoreAdminUi/js/entity-options',
    'Ergonode_CoreAdminUi/js/visibility-toggle',
    'mage/translate'
], function (entityOptions, visibilityToggle, $t) {
    'use strict';

    var panelSelector = '[data-role="source-panel"]';
    var sourcePanelSelector = panelSelector + '[data-source-panel="ergo"]';
    var mappingAutoMatchSelector = '[data-role="mapping-panel"] [data-role="auto-match"]';
    var autoMatchTitle = $t('Automatically connect languages to Store View locales');

    function hasExcludedItems(panel) {
        return Array.prototype.some.call(
            panel.querySelectorAll('[data-role="entity-card"] [data-role="source-active-toggle"]'),
            function (toggle) {
                return toggle.getAttribute('aria-pressed') !== 'true';
            }
        );
    }

    function createAction(options) {
        return entityOptions.createAction(options);
    }

    function buildActions(panel) {
        var actions = [];
        var source = panel.getAttribute('data-source-panel') || '';

        if (source === 'ergo' && panel.getAttribute('data-can-refresh') === '1') {
            actions.push(
                createAction({
                    role: 'refresh-ergonode',
                    className: 'vea-refresh-ergonode',
                    iconClass: 'vea-refresh-ergonode-icon',
                    label: $t('Refresh')
                })
            );
        }
        actions.push(
            createAction({
                role: 'visibility-toggle',
                className: 'veui-visibility-control',
                iconClass: 'veui-visibility-icon',
                label: $t('Excluded'),
                attributes: {
                    'aria-pressed': 'false',
                    'data-show-hint': panel.getAttribute('data-show-excluded-hint') || '',
                    'data-hide-hint': panel.getAttribute('data-hide-excluded-hint') || ''
                }
            })
        );

        return actions;
    }

    function syncPanel(panel) {
        var button = panel ? panel.querySelector('[data-role="visibility-toggle"]') : null;
        var available = panel ? hasExcludedItems(panel) : false;

        if (!button) {
            return false;
        }
        if (!available) {
            visibilityToggle.setVisible(button, false);
        }
        button.disabled = !available;

        return available;
    }

    function syncAutoMatch(root, availableCount) {
        var button = root ? root.querySelector(mappingAutoMatchSelector) : null;
        var count = Number.isFinite(Number(availableCount))
            ? Math.max(0, Math.floor(Number(availableCount)))
            : 0;
        var badge;
        var accessibleLabel;

        if (!button) {
            return count;
        }
        badge = button.querySelector('[data-role="auto-match-count"]');
        if (!badge && button.ownerDocument) {
            badge = button.ownerDocument.createElement('span');
            badge.className = 'veui-count vel-auto-match-count';
            badge.setAttribute('data-role', 'auto-match-count');
            badge.setAttribute('aria-hidden', 'true');
            button.appendChild(badge);
        }
        if (badge) {
            badge.textContent = String(count);
            badge.hidden = count === 0;
        }
        accessibleLabel = count > 0 ? autoMatchTitle + ': ' + String(count) : autoMatchTitle;
        button.disabled = count === 0;
        button.setAttribute('data-available-count', String(count));
        button.setAttribute('aria-label', accessibleLabel);
        button.title = accessibleLabel;

        return count;
    }

    function sync(root, autoMatchCount) {
        var sourcePanel = root ? root.querySelector(sourcePanelSelector) : null;
        var sourceAvailable = false;

        if (!root) {
            return false;
        }
        root.querySelectorAll(panelSelector).forEach(function (panel) {
            var available = syncPanel(panel);

            if (panel === sourcePanel) {
                sourceAvailable = available;
            }
        });
        syncAutoMatch(root, autoMatchCount);

        return sourceAvailable;
    }

    function initializePanel(panel) {
        var tools = panel ? panel.querySelector('.vel-side-tools') : null;
        var source = panel ? panel.getAttribute('data-source-panel') || '' : '';
        var menu = tools ? tools.querySelector('[data-source-options]') : null;
        var placeholder = tools
            ? tools.querySelector('[data-role="entity-options-placeholder"]')
            : null;

        if (!panel || !tools) {
            return null;
        }
        if (!menu) {
            menu = entityOptions.create({
                actions: buildActions(panel),
                menuLabel: panel.getAttribute('data-source-options-label') ||
                    $t(source === 'magento' ? 'Language actions: Magento' : 'Language actions: Ergonode')
            });
            menu.classList.add('veui-source-options');
            menu.classList.add('vel-source-options');
            menu.setAttribute('data-source-options', source);
            if (source === 'ergo') {
                menu.setAttribute('data-language-source-options', '1');
            }
            tools.appendChild(menu);
        }
        if (placeholder) {
            placeholder.remove();
        }
        visibilityToggle.initialize(menu);

        return menu;
    }

    function initialize(root) {
        var sourceMenu = null;

        if (!root) {
            return null;
        }
        root.querySelectorAll(panelSelector).forEach(function (panel) {
            var menu = initializePanel(panel);

            if (panel.getAttribute('data-source-panel') === 'ergo') {
                sourceMenu = menu;
            }
        });
        sync(root);

        return sourceMenu;
    }

    return {
        initialize: initialize,
        sync: sync
    };
});
